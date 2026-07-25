import './bootstrap';
import { gsap } from 'gsap';

const $ = (id) => document.getElementById(id);
const els = {
    app: $('app'), welcome: document.querySelector('[data-panel="welcome"]'), call: document.querySelector('[data-panel="call"]'),
    form: $('startForm'), name: $('displayName'), camera: $('cameraSelect'), mic: $('micSelect'), preview: $('localPreview'),
    local: $('localVideo'), remote: $('remoteVideo'), permission: $('permissionButton'), start: $('startButton'), error: $('formError'),
    status: $('statusText'), peer: $('peerName'), quality: $('qualityText'), duration: $('durationText'), announcer: $('announcer'),
    online: $('onlineCount'), searchOnline: $('searchOnlineCount'), callOnline: $('callOnlineCount'), waiting: $('waitingPanel'), availableList: $('availableList'),
    mute: $('muteButton'), cam: $('cameraButton'), flip: $('switchCameraButton'), full: $('fullscreenButton'), next: $('nextButton'),
    report: $('reportButton'), block: $('blockButton'), leave: $('leaveButton'), dialog: $('reportDialog'), reportReason: $('reportReason'),
    reportDescription: $('reportDescription'), sendReport: $('sendReportButton'),
};

const state = {
    session: null, room: null, peer: null, initiator: false,
    localStream: null, pc: null, sequence: 0, pendingIce: [], audio: true, video: true, facing: 'user',
    heartbeat: null, duration: null, quality: null, onlinePoll: null, statePoll: null, availablePoll: null, signalPoll: null, startedAt: null, connectionTimer: null, aborter: null,
    lastSignalId: 0,
    recovering: false,
    waitingTween: null,
    connectionTimeoutSeconds: 45,
};

const motion = !window.matchMedia('(prefers-reduced-motion: reduce)').matches;

function setStatus(value) {
    els.app.dataset.state = value;
    els.status.textContent = value[0].toUpperCase() + value.slice(1).replace('-', ' ');
    els.announcer.textContent = els.status.textContent;
}

function animateWelcome() {
    if (!motion) return;

    gsap.from('.brand, .hero-copy h1, .hero-copy .lead, .stats > div, .glass', {
        y: 18,
        opacity: 0,
        duration: 0.75,
        stagger: 0.07,
        ease: 'power3.out',
    });
}

function animateCallIn() {
    if (!motion) return;

    gsap.fromTo(els.call, { opacity: 0 }, { opacity: 1, duration: 0.35, ease: 'power2.out' });
    gsap.from('.topbar, .controls, .local', {
        y: 18,
        opacity: 0,
        duration: 0.55,
        stagger: 0.06,
        ease: 'power3.out',
    });
}

function animateMatched() {
    if (!motion) return;

    gsap.fromTo('.topbar', { scale: 0.98 }, { scale: 1, duration: 0.45, ease: 'back.out(1.7)' });
}

function animateEnded() {
    if (!motion) return;

    gsap.fromTo('.topbar', { y: -4 }, { y: 0, duration: 0.28, ease: 'power2.out' });
}

function startWaitingAnimation() {
    if (!motion || state.waitingTween) return;

    state.waitingTween = gsap.to('.pulse-ring', {
        rotate: 360,
        duration: 8,
        repeat: -1,
        ease: 'none',
    });
}

function stopWaitingAnimation() {
    state.waitingTween?.kill();
    state.waitingTween = null;
}

async function api(method, url, data = {}) {
    const response = await window.axios({ method, url, data, signal: state.aborter?.signal });
    return response.data;
}

function messageFrom(error, fallback) {
    return error?.response?.data?.message || fallback;
}

function handleAsyncError(error, fallback = 'The call action failed. Please try again.') {
    if (error?.name === 'CanceledError' || error?.code === 'ERR_CANCELED') return;

    const message = messageFrom(error, fallback);
    if (els.call.hidden) {
        els.error.textContent = message;
        setStatus('welcome');

        return;
    }

    els.status.textContent = message;
    els.announcer.textContent = message;
}

function asyncListener(handler, fallback) {
    return (event) => {
        Promise.resolve(handler(event)).catch((error) => handleAsyncError(error, fallback));
    };
}

async function refreshOnlineCount() {
    try {
        const { online, waiting } = await api('get', '/api/online');
        const text = String(online ?? 0);
        els.online.textContent = text;
        els.searchOnline.textContent = String(waiting ?? 0);
        els.callOnline.textContent = `${text} online`;
    } catch {
        els.callOnline.textContent = 'Online count unavailable';
    }
}

function normalizeRoom(room) {
    return {
        room_uuid: room.room_uuid || room.uuid,
        peer: room.peer,
        initiator: room.initiator,
        ice_servers: room.ice_servers || [],
        connection_timeout_seconds: room.connection_timeout_seconds || 25,
    };
}

function startStatePolling() {
    clearInterval(state.statePoll);
    state.statePoll = setInterval(async () => {
        if (!state.session || state.room || els.call.hidden) return;

        try {
            const current = await api('get', '/api/state');
            if (current.room) {
                await handleMatch(normalizeRoom(current.room));
            }
        } catch {
            // The regular heartbeat/auth flow will surface hard failures.
        }
    }, 2000);
}

function stopStatePolling() {
    clearInterval(state.statePoll);
    state.statePoll = null;
}

async function acceptMatchedRoom() {
    const current = await api('get', '/api/state');
    if (current.room) {
        await handleMatch(normalizeRoom(current.room));

        return true;
    }

    return false;
}

async function refreshAvailableParticipants() {
    if (!state.session || state.room || els.call.hidden) return;

    try {
        const { participants } = await api('get', '/api/matchmaking/available');
        renderAvailableParticipants(participants || []);
    } catch {
        renderAvailableParticipants([]);
    }
}

function startAvailablePolling() {
    refreshAvailableParticipants();
    clearInterval(state.availablePoll);
    state.availablePoll = setInterval(refreshAvailableParticipants, 5000);
}

function stopAvailablePolling() {
    clearInterval(state.availablePoll);
    state.availablePoll = null;
}

function renderAvailableParticipants(participants) {
    els.searchOnline.textContent = String(participants.length);
    els.availableList.replaceChildren();

    if (participants.length === 0) {
        const empty = document.createElement('p');
        empty.className = 'rounded-lg border border-white/10 bg-white/5 p-3 text-center text-sm text-slate-300';
        empty.textContent = 'No one is available to call yet. Keep this screen open.';
        els.availableList.append(empty);

        return;
    }

    for (const participant of participants) {
        const row = document.createElement('div');
        row.className = 'flex items-center justify-between gap-3 rounded-lg border border-cyan-200/15 bg-slate-900/85 p-3';

        const name = document.createElement('div');
        name.className = 'min-w-0';

        const title = document.createElement('strong');
        title.className = 'block truncate text-white';
        title.textContent = participant.display_name || 'Guest';

        const meta = document.createElement('span');
        meta.className = 'block text-xs text-cyan-100/80';
        meta.textContent = 'Available now';

        const button = document.createElement('button');
        button.className = 'rounded-lg bg-cyan-400 px-4 py-2 font-black text-slate-950 transition hover:-translate-y-0.5';
        button.type = 'button';
        button.textContent = 'Call';
        button.addEventListener('click', () => callParticipant(participant.uuid));

        name.append(title, meta);
        row.append(name, button);
        els.availableList.append(row);
    }
}

function startSignalPolling() {
    clearInterval(state.signalPoll);
    state.signalPoll = setInterval(async () => {
        if (!state.room || !state.pc) return;

        try {
            const { signals, room_ended: roomEnded } = await api('get', `/api/signals?room_uuid=${encodeURIComponent(state.room)}&after=${state.lastSignalId}`);
            if (roomEnded) {
                cleanupPeer(true);

                return;
            }

            for (const message of signals || []) {
                state.lastSignalId = Math.max(state.lastSignalId, Number(message.id) || 0);
                await handleSignal(message);
            }
        } catch {
            // Reverb remains the primary path; polling is a quiet fallback.
        }
    }, 900);
}

function stopSignalPolling() {
    clearInterval(state.signalPoll);
    state.signalPoll = null;
    state.lastSignalId = 0;
}

async function recoverConnection(reason = 'Connection failed. Finding a new match...') {
    if (state.recovering) return;

    state.recovering = true;
    els.peer.textContent = 'Waiting';
    els.waiting.hidden = false;
    startWaitingAnimation();
    setStatus('searching');
    els.status.textContent = reason;

    try {
        const joined = await api('post', '/api/rooms/retry');
        cleanupPeer(true);
        if (joined.room) {
            await acceptMatchedRoom();
        }
    } catch {
        cleanupPeer(true);
    } finally {
        state.recovering = false;
        startStatePolling();
        startAvailablePolling();
    }
}

function startOnlinePolling() {
    refreshOnlineCount();
    clearInterval(state.onlinePoll);
    state.onlinePoll = setInterval(refreshOnlineCount, 5000);
}

async function requestMedia() {
    stopStream(state.localStream);
    const constraints = {
        audio: els.mic.value ? { deviceId: { exact: els.mic.value } } : true,
        video: els.camera.value ? { deviceId: { exact: els.camera.value } } : { facingMode: state.facing },
    };
    state.localStream = await navigator.mediaDevices.getUserMedia(constraints);
    els.preview.srcObject = state.localStream;
    els.local.srcObject = state.localStream;
    await populateDevices();
}

async function populateDevices() {
    const devices = await navigator.mediaDevices.enumerateDevices();
    fillSelect(els.camera, devices.filter((d) => d.kind === 'videoinput'), 'Camera');
    fillSelect(els.mic, devices.filter((d) => d.kind === 'audioinput'), 'Microphone');
}

function fillSelect(select, devices, fallback) {
    const selected = select.value;
    select.replaceChildren(...devices.map((device, index) => {
        const option = document.createElement('option');
        option.value = device.deviceId;
        option.textContent = device.label || `${fallback} ${index + 1}`;
        return option;
    }));
    if ([...select.options].some((option) => option.value === selected)) select.value = selected;
}

async function start(event) {
    event.preventDefault();
    els.error.textContent = '';
    state.aborter = new AbortController();
    try {
        if (!state.localStream) await requestMedia();
        const payload = Object.fromEntries(new FormData(els.form));
        const created = await api('post', '/api/guest-sessions', payload);
        state.session = created.session;
        window.setCsrfToken?.(created.csrf_token);
        await window.refreshCsrfToken?.();
        showCall();
        setStatus('searching');
        const joined = await api('post', '/api/matchmaking/join');
        if (Number.isInteger(joined.available)) {
            els.searchOnline.textContent = String(joined.available);
        }
        if (joined.room) {
            await acceptMatchedRoom();
        }
        startStatePolling();
        startAvailablePolling();
        state.heartbeat = setInterval(() => api('post', '/api/guest-sessions/heartbeat').catch(() => {}), 5000);
    } catch (error) {
        const message = error.response?.data?.message || 'Connection setup failed. Retrying safely...';
        if (state.session && !els.call.hidden) {
            els.peer.textContent = 'Waiting';
            els.status.textContent = message;
            startStatePolling();
            startAvailablePolling();

            return;
        }

        els.error.textContent = message;
        setStatus('welcome');
    }
}

async function handleMatch(event) {
    if (state.room === event.room_uuid && state.pc) return;

    state.room = event.room_uuid;
    state.peer = event.peer;
    state.initiator = event.initiator;
    state.connectionTimeoutSeconds = Number(event.connection_timeout_seconds || 45);
    els.peer.textContent = event.peer?.display_name || 'Participant';
    els.waiting.hidden = false;
    setStatus('connecting');
    animateMatched();
    stopStatePolling();
    stopAvailablePolling();
    await createPeerConnection(event.ice_servers || []);
    startSignalPolling();
    if (state.initiator) {
        const offer = await state.pc.createOffer();
        await state.pc.setLocalDescription(offer);
        await sendSignal('offer', { sdp: offer.sdp });
    }
}

async function createPeerConnection(iceServers) {
    cleanupPeer(false);
    state.pc = new RTCPeerConnection({ iceServers });
    state.localStream.getTracks().forEach((track) => state.pc.addTrack(track, state.localStream));
    state.pc.onicecandidate = ({ candidate }) => {
        if (candidate) {
            sendSignal('ice-candidate', { candidate: candidate.toJSON() })
                .catch((error) => handleAsyncError(error, 'Could not exchange network details with the participant.'));
        }
    };
    state.pc.ontrack = ({ streams }) => {
        els.remote.srcObject = streams[0];
        els.waiting.hidden = true;
        stopWaitingAnimation();
        setStatus('connected');
        startTimers();
    };
    state.pc.onconnectionstatechange = () => {
        const current = state.pc.connectionState;
        if (current === 'failed') recoverConnection();
        if (current === 'disconnected') setStatus('reconnecting');
        if (current === 'closed') setStatus('ended');
    };
    state.connectionTimer = setTimeout(() => {
        if (!['connected', 'completed'].includes(state.pc?.iceConnectionState)) recoverConnection('Could not connect media. Retrying...');
    }, Math.max(30, state.connectionTimeoutSeconds) * 1000);
}

async function handleSignal({ room_uuid, signal }) {
    if (room_uuid !== state.room || !state.pc) return;
    const { type, payload } = signal;
    if (type === 'offer') {
        await state.pc.setRemoteDescription({ type: 'offer', sdp: payload.sdp });
        await flushIce();
        const answer = await state.pc.createAnswer();
        await state.pc.setLocalDescription(answer);
        await sendSignal('answer', { sdp: answer.sdp });
    }
    if (type === 'answer') {
        await state.pc.setRemoteDescription({ type: 'answer', sdp: payload.sdp });
        await flushIce();
    }
    if (type === 'ice-candidate') {
        if (!state.pc.remoteDescription) state.pendingIce.push(payload.candidate);
        else await state.pc.addIceCandidate(payload.candidate);
    }
    if (type === 'hangup') endCall('The call ended.', true);
    if (type === 'ice-restart' && state.initiator) restartIce();
}

async function flushIce() {
    while (state.pendingIce.length) await state.pc.addIceCandidate(state.pendingIce.shift());
}

async function sendSignal(type, payload = {}) {
    if (!state.room) return;
    await api('post', '/api/signals', { room_uuid: state.room, sequence: ++state.sequence, type, payload });
}

async function callParticipant(targetUuid) {
    if (!targetUuid || state.room) return;

    els.status.textContent = 'Calling...';
    stopAvailablePolling();

    try {
        const result = await api('post', '/api/matchmaking/call', { target_uuid: targetUuid });
        if (result.room) {
            await acceptMatchedRoom();
        }
    } catch (error) {
        els.status.textContent = error.response?.data?.message || 'That participant is no longer available.';
        startAvailablePolling();
    }
}

function showCall() {
    els.welcome.hidden = true;
    els.call.hidden = false;
    els.waiting.hidden = false;
    els.local.srcObject = state.localStream;
    animateCallIn();
    startWaitingAnimation();
}

function startTimers() {
    if (!state.startedAt) state.startedAt = Date.now();
    clearInterval(state.duration);
    clearInterval(state.quality);
    state.duration = setInterval(() => {
        const elapsed = Math.floor((Date.now() - state.startedAt) / 1000);
        els.duration.textContent = `${String(Math.floor(elapsed / 60)).padStart(2, '0')}:${String(elapsed % 60).padStart(2, '0')}`;
    }, 1000);
    state.quality = setInterval(updateQuality, 4000);
}

async function updateQuality() {
    if (!state.pc) return;
    const stats = await state.pc.getStats();
    let packetsLost = 0;
    stats.forEach((report) => { if (report.type === 'inbound-rtp') packetsLost += report.packetsLost || 0; });
    els.quality.textContent = `Quality: ${packetsLost > 20 ? 'unstable' : 'good'}`;
}

function updatePeerMedia(peerState) {
    els.status.textContent = `Connected${peerState?.audio === false ? ' - peer muted' : ''}${peerState?.video === false ? ' - peer camera off' : ''}`;
}

async function toggleAudio() {
    state.audio = !state.audio;
    state.localStream?.getAudioTracks().forEach((track) => track.enabled = state.audio);
    els.mute.textContent = state.audio ? 'Mic' : 'Muted';
    await sendSignal('media-state', { audio: state.audio, video: state.video });
}

async function toggleVideo() {
    state.video = !state.video;
    state.localStream?.getVideoTracks().forEach((track) => track.enabled = state.video);
    els.cam.textContent = state.video ? 'Cam' : 'Camera off';
    await sendSignal('media-state', { audio: state.audio, video: state.video });
}

async function switchCamera() {
    state.facing = state.facing === 'user' ? 'environment' : 'user';
    await requestMedia();
    const track = state.localStream.getVideoTracks()[0];
    const sender = state.pc?.getSenders().find((item) => item.track?.kind === 'video');
    if (sender && track) await sender.replaceTrack(track);
}

async function restartIce() {
    if (!state.pc) return;
    state.pc.restartIce();
    const offer = await state.pc.createOffer({ iceRestart: true });
    await state.pc.setLocalDescription(offer);
    await sendSignal('offer', { sdp: offer.sdp });
}

async function endCall(message = 'Call ended.', remote = false) {
    if (!remote) await sendSignal('hangup', {});
    cleanupPeer(true);
    setStatus('ended');
    els.waiting.hidden = true;
    stopWaitingAnimation();
    animateEnded();
    els.peer.textContent = message;
}

function cleanupPeer(resetRoom) {
    clearTimeout(state.connectionTimer);
    clearInterval(state.duration);
    clearInterval(state.quality);
    state.pc?.close();
    state.pc = null;
    state.pendingIce = [];
    state.startedAt = null;
    stopSignalPolling();
    if (resetRoom) {
        state.room = null;
        startStatePolling();
        startAvailablePolling();
    }
}

function stopStream(stream) {
    stream?.getTracks().forEach((track) => track.stop());
}

async function report() {
    await api('post', '/api/reports', { room_uuid: state.room, reason: els.reportReason.value, description: els.reportDescription.value });
}

els.permission.addEventListener('click', asyncListener(requestMedia, 'Permission denied. Allow camera and microphone access, then retry.'));
els.camera.addEventListener('change', asyncListener(requestMedia, 'Could not switch camera. Please retry.'));
els.mic.addEventListener('change', asyncListener(requestMedia, 'Could not switch microphone. Please retry.'));
els.form.addEventListener('submit', asyncListener(start, 'Connection setup failed. Please retry.'));
els.mute.addEventListener('click', asyncListener(toggleAudio, 'Could not update microphone state.'));
els.cam.addEventListener('click', asyncListener(toggleVideo, 'Could not update camera state.'));
els.flip.addEventListener('click', asyncListener(switchCamera, 'Could not switch camera. Please retry.'));
els.full.addEventListener('click', () => els.remote.requestFullscreen?.());
els.leave.addEventListener('click', asyncListener(async () => { await api('post', '/api/rooms/leave').catch(() => {}); await endCall('You left.', true); }, 'Could not leave the room cleanly.'));
els.next.addEventListener('click', asyncListener(async () => {
    cleanupPeer(true);
    els.waiting.hidden = false;
    startWaitingAnimation();
    setStatus('searching');
    const joined = await api('post', '/api/rooms/next');
    if (joined.room) {
        await acceptMatchedRoom();
    }
}, 'Could not find the next participant. Please retry.'));
els.report.addEventListener('click', () => els.dialog.showModal());
els.sendReport.addEventListener('click', asyncListener(async (event) => { event.preventDefault(); await report(); els.dialog.close(); }, 'Could not send the report. Please retry.'));
els.block.addEventListener('click', asyncListener(async () => { await api('post', '/api/blocks', { room_uuid: state.room }); await endCall('Blocked and left.', true); }, 'Could not block this participant. Please retry.'));
window.addEventListener('beforeunload', () => {
    const token = document.querySelector('meta[name="csrf-token"]')?.content || '';
    navigator.sendBeacon('/api/rooms/leave', new URLSearchParams({ _token: token }));
});

populateDevices().catch(() => {});
startOnlinePolling();
animateWelcome();
