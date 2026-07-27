<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>Image PDF Studio</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-zinc-950 text-zinc-50 antialiased">
    <main id="app" class="min-h-screen">
        <section class="mx-auto grid min-h-screen w-[min(1180px,calc(100%-28px))] grid-cols-1 gap-6 py-6 lg:grid-cols-[minmax(0,1fr)_360px] lg:py-8">
            <div class="flex min-h-0 flex-col gap-5">
                <header class="flex flex-wrap items-center justify-between gap-4 border-b border-white/10 pb-5">
                    <div>
                        <p class="text-sm font-bold uppercase tracking-normal text-emerald-300">Private browser tool</p>
                        <h1 class="mt-2 text-3xl font-black text-white sm:text-5xl">Image PDF Studio</h1>
                        <p class="mt-3 max-w-2xl text-base leading-7 text-zinc-300">Convert multiple images into one clean PDF. Your files stay in this browser and are not uploaded to the backend.</p>
                    </div>
                    <div class="rounded-lg border border-emerald-300/30 bg-emerald-300/10 px-4 py-3 text-sm font-bold text-emerald-100">
                        <span id="fileCount">0</span> images ready
                    </div>
                </header>

                <section id="dropZone" class="grid min-h-64 place-items-center rounded-lg border-2 border-dashed border-zinc-600 bg-zinc-900/70 p-6 text-center transition">
                    <div class="grid max-w-xl gap-4">
                        <div class="mx-auto grid h-16 w-16 place-items-center rounded-lg border border-emerald-300/30 bg-emerald-300/10 text-2xl font-black text-emerald-200">PDF</div>
                        <div>
                            <h2 class="text-2xl font-black text-white">Drop images here</h2>
                            <p class="mt-2 text-sm leading-6 text-zinc-300">PNG, JPG, JPEG, WEBP, GIF, and BMP files are supported. First image becomes page one.</p>
                        </div>
                        <div class="flex flex-wrap justify-center gap-3">
                            <label class="inline-flex cursor-pointer items-center justify-center rounded-lg bg-emerald-400 px-5 py-3 font-black text-zinc-950 transition hover:-translate-y-0.5">
                                Select Images
                                <input id="imageInput" class="sr-only" type="file" accept="image/*" multiple>
                            </label>
                            <button id="clearButton" class="rounded-lg border border-white/15 bg-zinc-800 px-5 py-3 font-bold text-white transition hover:-translate-y-0.5 hover:border-zinc-300" type="button">Clear</button>
                        </div>
                    </div>
                </section>

                <section class="min-h-0">
                    <div class="mb-3 flex items-center justify-between gap-3">
                        <h2 class="text-lg font-black text-white">Page Order</h2>
                        <span class="text-sm text-zinc-400">Use up/down to arrange pages</span>
                    </div>
                    <div id="emptyState" class="rounded-lg border border-white/10 bg-zinc-900 p-5 text-zinc-300">No images selected yet.</div>
                    <div id="imageList" class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3"></div>
                </section>
            </div>

            <aside class="h-fit rounded-lg border border-white/10 bg-zinc-900 p-5 shadow-2xl shadow-black/30 lg:sticky lg:top-8">
                <form id="pdfForm" class="grid gap-5">
                    <div>
                        <h2 class="text-xl font-black text-white">PDF Settings</h2>
                        <p id="statusText" class="mt-2 min-h-6 text-sm text-zinc-300" role="status">Select images to begin.</p>
                    </div>

                    <label class="grid gap-2 text-sm font-bold text-zinc-200">
                        File name
                        <input id="fileName" class="rounded-lg border border-white/10 bg-zinc-950 px-3 py-3 text-white focus:border-emerald-300" type="text" value="converted-images.pdf" autocomplete="off">
                    </label>

                    <div class="grid grid-cols-2 gap-3">
                        <label class="grid gap-2 text-sm font-bold text-zinc-200">
                            Page size
                            <select id="pageSize" class="rounded-lg border border-white/10 bg-zinc-950 px-3 py-3 text-white focus:border-emerald-300">
                                <option value="a4">A4</option>
                                <option value="letter">Letter</option>
                                <option value="fit">Fit image</option>
                            </select>
                        </label>
                        <label class="grid gap-2 text-sm font-bold text-zinc-200">
                            Orientation
                            <select id="orientation" class="rounded-lg border border-white/10 bg-zinc-950 px-3 py-3 text-white focus:border-emerald-300">
                                <option value="auto">Auto</option>
                                <option value="portrait">Portrait</option>
                                <option value="landscape">Landscape</option>
                            </select>
                        </label>
                    </div>

                    <label class="grid gap-2 text-sm font-bold text-zinc-200">
                        Margin: <span><span id="marginValue">12</span> mm</span>
                        <input id="margin" class="accent-emerald-300" type="range" min="0" max="30" value="12">
                    </label>

                    <label class="grid gap-2 text-sm font-bold text-zinc-200">
                        Image quality: <span><span id="qualityValue">86</span>%</span>
                        <input id="quality" class="accent-emerald-300" type="range" min="55" max="100" value="86">
                    </label>

                    <label class="grid gap-2 text-sm font-bold text-zinc-200">
                        Background
                        <select id="background" class="rounded-lg border border-white/10 bg-zinc-950 px-3 py-3 text-white focus:border-emerald-300">
                            <option value="white">White</option>
                            <option value="transparent">Transparent</option>
                            <option value="black">Black</option>
                        </select>
                    </label>

                    <button id="downloadButton" class="rounded-lg bg-emerald-400 px-5 py-3 font-black text-zinc-950 transition hover:-translate-y-0.5 disabled:cursor-not-allowed disabled:opacity-45 disabled:hover:translate-y-0" type="submit" disabled>Download PDF</button>
                </form>
            </aside>
        </section>
    </main>
</body>
</html>
