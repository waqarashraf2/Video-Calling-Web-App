<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'SignalRoom') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen overflow-x-hidden bg-slate-950 text-slate-50 antialiased">
    <main id="app" class="shell relative isolate min-h-screen" data-state="welcome">
        <div class="aurora pointer-events-none fixed -inset-24 -z-10" aria-hidden="true"></div>

        <section class="welcome mx-auto grid min-h-screen w-[min(1180px,calc(100%-32px))] grid-cols-1 items-center gap-8 py-10 lg:grid-cols-[minmax(0,1fr)_minmax(360px,520px)]" data-panel="welcome">
            <div class="hero-copy grid gap-5">
                <div class="brand flex items-center gap-3 font-extrabold text-cyan-200" aria-label="SignalRoom">
                    <span class="h-7 w-7 rounded-lg bg-gradient-to-br from-cyan-300 via-violet-500 to-rose-400 shadow-[0_0_34px_rgba(34,211,238,.45)]"></span>
                    SignalRoom
                </div>
                <h1 class="max-w-3xl text-[clamp(2.4rem,6vw,5rem)] font-black leading-[.95] tracking-normal text-white">Private random video, without recordings.</h1>
                <p class="max-w-2xl text-lg leading-8 text-slate-300">Meet one adult at a time. Media flows peer-to-peer through WebRTC; this application coordinates sessions, safety, and signaling only.</p>
                <div class="stats grid max-w-2xl grid-cols-1 gap-3 sm:grid-cols-3" aria-label="Live service status">
                    <div class="rounded-lg border border-cyan-200/20 bg-slate-950/60 p-4 shadow-inner shadow-white/5 backdrop-blur">
                        <strong id="onlineCount" class="block text-2xl font-black leading-none text-cyan-200">0</strong>
                        <span class="mt-2 block text-sm text-slate-300">online now</span>
                    </div>
                    <div class="rounded-lg border border-cyan-200/20 bg-slate-950/60 p-4 shadow-inner shadow-white/5 backdrop-blur">
                        <strong class="block text-2xl font-black leading-none text-cyan-200">1:1</strong>
                        <span class="mt-2 block text-sm text-slate-300">private rooms</span>
                    </div>
                    <div class="rounded-lg border border-cyan-200/20 bg-slate-950/60 p-4 shadow-inner shadow-white/5 backdrop-blur">
                        <strong class="block text-2xl font-black leading-none text-cyan-200">0</strong>
                        <span class="mt-2 block text-sm text-slate-300">recordings stored</span>
                    </div>
                </div>
            </div>

            <form id="startForm" class="glass grid gap-4 rounded-lg border border-cyan-200/25 bg-slate-950/75 p-5 shadow-2xl shadow-black/40 backdrop-blur-xl" novalidate>
                <label class="grid gap-2 font-bold text-blue-100">
                    Display name
                    <input id="displayName" class="rounded-lg border border-slate-400/30 bg-slate-900/90 px-3 py-3 text-white outline-none transition focus:border-cyan-200 focus:ring-4 focus:ring-cyan-200/30" name="display_name" maxlength="30" minlength="2" autocomplete="nickname" placeholder="Your first name or alias" required>
                </label>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <label class="grid gap-2 font-bold text-blue-100">
                        Camera
                        <select id="cameraSelect" class="rounded-lg border border-slate-400/30 bg-slate-900/90 px-3 py-3 text-white outline-none transition focus:border-cyan-200 focus:ring-4 focus:ring-cyan-200/30" aria-label="Camera device"></select>
                    </label>
                    <label class="grid gap-2 font-bold text-blue-100">
                        Microphone
                        <select id="micSelect" class="rounded-lg border border-slate-400/30 bg-slate-900/90 px-3 py-3 text-white outline-none transition focus:border-cyan-200 focus:ring-4 focus:ring-cyan-200/30" aria-label="Microphone device"></select>
                    </label>
                </div>
                <div class="preview-wrap relative grid min-h-56 place-items-center overflow-hidden rounded-lg border border-slate-400/25 bg-slate-950">
                    <video id="localPreview" class="absolute inset-0 h-full w-full object-cover" muted playsinline autoplay aria-label="Local camera preview"></video>
                    <button type="button" id="permissionButton" class="relative z-10 rounded-lg border border-cyan-200/30 bg-cyan-300/15 px-4 py-3 font-bold text-white transition hover:-translate-y-0.5 hover:border-cyan-200">Enable camera and mic</button>
                </div>
                <label class="flex items-center gap-3 font-semibold text-blue-100"><input id="adult" class="h-5 w-5 accent-cyan-300" name="adult" type="checkbox" required> I confirm I am at least 18 years old.</label>
                <label class="flex items-center gap-3 font-semibold text-blue-100"><input id="terms" class="h-5 w-5 accent-cyan-300" name="terms" type="checkbox" required> I accept the Terms of Use and Community Guidelines.</label>
                <details class="rounded-lg border border-white/10 bg-white/5 p-3 text-slate-300">
                    <summary class="cursor-pointer font-bold text-white">Community Guidelines</summary>
                    <p class="mt-2">Be respectful. No nudity, harassment, hate, scams, dangerous conduct, or minors. Reports store limited metadata only for moderation and expire by policy.</p>
                </details>
                <button id="startButton" class="rounded-lg border border-transparent bg-gradient-to-br from-cyan-500 via-violet-600 to-rose-400 px-4 py-3 font-extrabold text-white shadow-lg shadow-violet-950/40 transition hover:-translate-y-0.5" type="submit">Start Video Chat</button>
                <p id="formError" class="min-h-5 text-rose-200" role="alert"></p>
                <p class="text-sm leading-6 text-slate-300">We do not record, store, proxy, or inspect your video/audio. Production use requires HTTPS and a real TURN service.</p>
            </form>
        </section>

        <section class="call relative min-h-screen overflow-hidden bg-slate-950" data-panel="call" hidden>
            <video id="remoteVideo" class="h-screen min-h-screen w-full bg-gradient-to-br from-slate-950 to-slate-900 object-cover" playsinline autoplay aria-label="Remote participant video"></video>
            <div id="waitingPanel" class="waiting-panel pointer-events-none absolute inset-0 grid place-content-center justify-items-center gap-3 bg-[radial-gradient(circle,rgba(15,23,42,.58),transparent_42%)] text-center">
                <div class="pulse-ring h-24 w-24 rounded-full border-2 border-cyan-200/75 shadow-[0_0_0_16px_rgba(103,232,249,.08),0_0_48px_rgba(139,92,246,.4)]" aria-hidden="true"></div>
                <strong class="text-[clamp(1.7rem,4vw,3rem)] font-black">Choose someone online</strong>
                <span class="text-slate-300"><b id="searchOnlineCount">0</b> people available right now</span>
                <div id="availableList" class="pointer-events-auto mt-4 grid max-h-72 w-[min(520px,calc(100vw-32px))] gap-2 overflow-y-auto rounded-lg border border-cyan-200/20 bg-slate-950/80 p-3 text-left shadow-2xl shadow-black/40 backdrop-blur-xl"></div>
            </div>
            <video id="localVideo" class="local absolute right-5 top-24 aspect-[3/4] w-[min(240px,34vw)] rounded-lg border border-cyan-200/50 bg-slate-900 object-cover shadow-2xl shadow-black/60" muted playsinline autoplay aria-label="Local video preview"></video>
            <div class="topbar absolute left-1/2 top-4 flex w-[min(840px,calc(100%-28px))] -translate-x-1/2 justify-between gap-4 rounded-lg border border-cyan-200/20 bg-slate-950/70 px-4 py-3 shadow-xl shadow-black/30 backdrop-blur-xl">
                <div>
                    <strong id="peerName" class="block font-black text-white">Waiting</strong>
                    <span id="statusText" class="block text-sm text-cyan-200">Preparing</span>
                </div>
                <div class="grid gap-1 text-right text-sm">
                    <span id="qualityText" class="text-cyan-200">Quality: --</span>
                    <span id="callOnlineCount" class="text-slate-300">0 online</span>
                </div>
            </div>
            <div class="controls absolute bottom-5 left-1/2 flex max-w-[calc(100%-24px)] -translate-x-1/2 items-center gap-2 overflow-x-auto rounded-lg border border-cyan-200/20 bg-slate-950/75 p-3 shadow-2xl shadow-black/50 backdrop-blur-xl" aria-label="Call controls">
                <button id="muteButton" class="grid aspect-square min-w-14 place-items-center rounded-full border border-white/15 bg-slate-900/90 p-0 font-extrabold text-white transition hover:-translate-y-0.5 hover:border-cyan-200" aria-label="Mute microphone"><span>Mic</span></button>
                <button id="cameraButton" class="grid aspect-square min-w-14 place-items-center rounded-full border border-white/15 bg-slate-900/90 p-0 font-extrabold text-white transition hover:-translate-y-0.5 hover:border-cyan-200" aria-label="Turn camera off"><span>Cam</span></button>
                <button id="switchCameraButton" class="grid aspect-square min-w-14 place-items-center rounded-full border border-white/15 bg-slate-900/90 p-0 font-extrabold text-white transition hover:-translate-y-0.5 hover:border-cyan-200" aria-label="Switch camera"><span>Flip</span></button>
                <button id="fullscreenButton" class="grid aspect-square min-w-14 place-items-center rounded-full border border-white/15 bg-slate-900/90 p-0 font-extrabold text-white transition hover:-translate-y-0.5 hover:border-cyan-200" aria-label="Full screen remote video"><span>Full</span></button>
                <button id="nextButton" class="min-h-12 whitespace-nowrap rounded-lg border border-white/15 bg-slate-900/90 px-4 font-extrabold text-white transition hover:-translate-y-0.5 hover:border-cyan-200">Next Person</button>
                <button id="reportButton" class="min-h-12 whitespace-nowrap rounded-lg border border-rose-300/20 bg-rose-400/15 px-4 font-extrabold text-white transition hover:-translate-y-0.5 hover:border-rose-200">Report</button>
                <button id="blockButton" class="min-h-12 whitespace-nowrap rounded-lg border border-rose-300/20 bg-rose-400/15 px-4 font-extrabold text-white transition hover:-translate-y-0.5 hover:border-rose-200">Block</button>
                <button id="leaveButton" class="min-h-12 whitespace-nowrap rounded-lg border border-transparent bg-rose-600 px-4 font-extrabold text-white transition hover:-translate-y-0.5">Leave</button>
            </div>
            <div class="duration absolute bottom-7 left-4 rounded-lg border border-cyan-200/20 bg-slate-950/70 px-3 py-2 text-cyan-200 backdrop-blur-xl" id="durationText">00:00</div>
        </section>

        <dialog id="reportDialog" class="w-[min(460px,calc(100%-32px))] rounded-lg border border-cyan-200/25 bg-slate-950/90 p-5 text-white shadow-2xl backdrop:bg-black/70">
            <form method="dialog" class="grid gap-4">
                <h2 class="text-xl font-black">Report participant</h2>
                <label class="grid gap-2 font-bold text-blue-100">Reason
                    <select id="reportReason" class="rounded-lg border border-slate-400/30 bg-slate-900 px-3 py-3 text-white">
                        <option value="nudity_or_sexual_content">Nudity or sexual content</option>
                        <option value="harassment_or_threats">Harassment or threats</option>
                        <option value="hate_speech">Hate speech</option>
                        <option value="suspected_minor">Suspected minor</option>
                        <option value="spam_or_scam">Spam or scam</option>
                        <option value="dangerous_or_illegal_behavior">Dangerous or illegal behavior</option>
                        <option value="other">Other</option>
                    </select>
                </label>
                <label class="grid gap-2 font-bold text-blue-100">Description
                    <textarea id="reportDescription" class="rounded-lg border border-slate-400/30 bg-slate-900 px-3 py-3 text-white" maxlength="500" rows="4"></textarea>
                </label>
                <menu class="m-0 flex justify-end gap-3 p-0">
                    <button value="cancel" class="rounded-lg border border-white/15 bg-slate-900 px-4 py-2">Cancel</button>
                    <button id="sendReportButton" value="default" class="rounded-lg bg-gradient-to-br from-cyan-500 via-violet-600 to-rose-400 px-4 py-2 font-extrabold">Send report</button>
                </menu>
            </form>
        </dialog>
        <div id="announcer" class="sr-only" aria-live="polite"></div>
    </main>
</body>
</html>
