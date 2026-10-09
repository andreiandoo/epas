/* competitie.tixello.ro — scena 3D din hero, construită integral din cod (fără modele sau texturi
   descărcate): suprafața de concurs se asamblează placă cu placă, iar două centuri — aka (roșu)
   și shiro (alb), culorile celor doi sportivi din kumite — se rotesc în jurul ei. */
import * as THREE from 'https://cdn.jsdelivr.net/npm/three@0.160.0/build/three.module.min.js';

const canvas = document.querySelector('[data-hero3d]');
if (canvas) {
    try { start(canvas); } catch (err) { /* fără WebGL rămâne fundalul din CSS */ }
}

function start(canvas) {
    const host = canvas.parentElement;
    const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const small = window.matchMedia('(max-width: 1023px)');

    const renderer = new THREE.WebGLRenderer({ canvas, antialias: true, alpha: true, powerPreference: 'high-performance' });
    renderer.setClearColor(0x000000, 0);
    renderer.toneMapping = THREE.ACESFilmicToneMapping;
    renderer.toneMappingExposure = 1.15;

    const scene = new THREE.Scene();
    scene.fog = new THREE.Fog(0x01012f, 15, 36);

    const camera = new THREE.PerspectiveCamera(36, 1, 0.1, 120);

    /* ---- Lumini (fără atenuare cu distanța, ca intensitățile să fie previzibile) ---- */
    scene.add(new THREE.HemisphereLight(0x8fa8ff, 0x01012f, 0.85));
    const key = new THREE.SpotLight(0xffffff, 3.2, 0, 0.5, 0.85, 0);
    key.position.set(5, 14, 7);
    scene.add(key, key.target);
    const fill = new THREE.SpotLight(0x3d7bff, 3.4, 0, 0.7, 1, 0);
    fill.position.set(-10, 9, -6);
    scene.add(fill, fill.target);
    const rim = new THREE.PointLight(0xe01020, 1.6, 0, 0);
    rim.position.set(-7, 1.6, 6);
    scene.add(rim);

    /* ---- Podeaua sălii: prinde conul de lumină ---- */
    const floor = new THREE.Mesh(
        new THREE.CircleGeometry(40, 64),
        new THREE.MeshStandardMaterial({ color: 0x060b44, roughness: 0.92, metalness: 0.05 })
    );
    floor.rotation.x = -Math.PI / 2;
    floor.position.y = -0.07;
    scene.add(floor);

    /* ---- Tatami: 10 × 10 plăci, 8 × 8 albastre cu bordură roșie de siguranță ---- */
    const N = 10, HALF = (N - 1) / 2;
    const tileGeo = new THREE.BoxGeometry(0.965, 0.12, 0.965);
    const tileMat = new THREE.MeshStandardMaterial({ roughness: 0.62, metalness: 0.04 });
    const tiles = new THREE.InstancedMesh(tileGeo, tileMat, N * N);
    const blueA = new THREE.Color(0x1151d3), blueB = new THREE.Color(0x0d45ba), red = new THREE.Color(0xd0101e);
    const meta = [];
    let seed = 7;
    const rand = () => { seed = (seed * 16807) % 2147483647; return (seed - 1) / 2147483646; };
    for (let ix = 0; ix < N; ix++) {
        for (let iz = 0; iz < N; iz++) {
            const i = ix * N + iz;
            const x = ix - HALF, z = iz - HALF;
            const border = ix === 0 || iz === 0 || ix === N - 1 || iz === N - 1;
            tiles.setColorAt(i, border ? red : ((ix + iz) % 2 ? blueA : blueB));
            const dist = Math.hypot(x, z);
            meta.push({
                x, z, dist,
                delay: 0.25 + dist * 0.085 + rand() * 0.3,
                drop: 6 + rand() * 6,
                spinX: (rand() - 0.5) * 2.4,
                spinZ: (rand() - 0.5) * 2.4,
            });
        }
    }
    tiles.instanceColor.needsUpdate = true;
    scene.add(tiles);

    // Liniile de start ale celor doi sportivi
    const lineMat = new THREE.MeshStandardMaterial({ color: 0xffffff, roughness: 0.5, emissive: 0xffffff, emissiveIntensity: 0.25 });
    const lines = [-1.5, 1.5].map((x) => {
        const m = new THREE.Mesh(new THREE.BoxGeometry(0.1, 0.02, 1), lineMat);
        m.position.set(x, 0.068, 0);
        scene.add(m);
        return m;
    });

    /* ---- Centurile: benzi construite vertex cu vertex, pe o orbită care unduiește ---- */
    const SEG = 150;
    function makeBelt(color, radius, arc, phase, speed, height, glow) {
        const geo = new THREE.BufferGeometry();
        const pos = new Float32Array((SEG + 1) * 2 * 3);
        const idx = [];
        for (let i = 0; i < SEG; i++) {
            const a = i * 2, b = a + 1, c = a + 2, d = a + 3;
            idx.push(a, b, c, b, d, c);
        }
        geo.setAttribute('position', new THREE.BufferAttribute(pos, 3));
        geo.setIndex(idx);
        const mesh = new THREE.Mesh(geo, new THREE.MeshStandardMaterial({
            color, roughness: 0.42, metalness: 0.15, side: THREE.DoubleSide, emissive: color, emissiveIntensity: glow,
        }));
        mesh.frustumCulled = false;
        scene.add(mesh);
        return { geo, pos, mesh, radius, arc, phase, speed, height };
    }
    const belts = [
        makeBelt(0xe01020, 7.3, Math.PI * 1.15, 0, 0.22, 1.7, 0.35),
        makeBelt(0xf4f7ff, 8.2, Math.PI * 1.05, Math.PI, 0.22, 2.4, 0.12),
    ];
    const vC = new THREE.Vector3(), vW = new THREE.Vector3(), vR = new THREE.Vector3(), UP = new THREE.Vector3(0, 1, 0);
    function updateBelt(b, t, grow) {
        const start = b.phase + t * b.speed;
        for (let i = 0; i <= SEG; i++) {
            const u = i / SEG;
            const a = start + u * b.arc * grow;
            const wob = Math.sin(a * 2.0 + t * 0.9);
            vC.set(Math.cos(a) * b.radius, b.height + Math.sin(a * 3 + t * 0.8) * 0.55, Math.sin(a) * b.radius);
            vR.set(Math.cos(a), 0, Math.sin(a));
            // Lățimea benzii se răsucește între „în sus” și „spre exterior”
            vW.copy(UP).multiplyScalar(Math.cos(wob * 1.1)).addScaledVector(vR, Math.sin(wob * 1.1)).normalize();
            // Capetele se îngustează, ca niște vârfuri de centură
            const taper = Math.min(1, u * 14, (1 - u) * 14);
            const w = 0.24 * (0.25 + 0.75 * taper);
            const o = i * 6;
            b.pos[o] = vC.x + vW.x * w; b.pos[o + 1] = vC.y + vW.y * w; b.pos[o + 2] = vC.z + vW.z * w;
            b.pos[o + 3] = vC.x - vW.x * w; b.pos[o + 4] = vC.y - vW.y * w; b.pos[o + 5] = vC.z - vW.z * w;
        }
        b.geo.attributes.position.needsUpdate = true;
        b.geo.computeVertexNormals();
    }

    /* ---- Praful din lumina reflectoarelor ---- */
    const DUST = 420;
    const dustPos = new Float32Array(DUST * 3);
    for (let i = 0; i < DUST; i++) {
        dustPos[i * 3] = (rand() - 0.5) * 30;
        dustPos[i * 3 + 1] = rand() * 11;
        dustPos[i * 3 + 2] = (rand() - 0.5) * 30;
    }
    const dustGeo = new THREE.BufferGeometry();
    dustGeo.setAttribute('position', new THREE.BufferAttribute(dustPos, 3));
    const dust = new THREE.Points(dustGeo, new THREE.PointsMaterial({
        color: 0xa9c2ff, size: 0.055, transparent: true, opacity: 0.6, depthWrite: false, blending: THREE.AdditiveBlending, sizeAttenuation: true,
    }));
    scene.add(dust);

    /* ---- Stare: cursor, derulare, dimensiune ---- */
    const pointer = { x: 0, y: 0, tx: 0, ty: 0 };
    window.addEventListener('pointermove', (e) => {
        pointer.tx = (e.clientX / window.innerWidth - 0.5) * 2;
        pointer.ty = (e.clientY / window.innerHeight - 0.5) * 2;
    }, { passive: true });

    let scrollP = 0;
    const onScroll = () => { scrollP = Math.min(1, Math.max(0, window.scrollY / Math.max(1, host.offsetHeight))); };
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();

    function resize() {
        // Dimensiunea o dă CSS-ul canvasului: pe telefon scena ocupă doar partea de sus a hero-ului
        const w = canvas.clientWidth || host.clientWidth, h = canvas.clientHeight || host.clientHeight;
        if (!w || !h) { return; }
        renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, small.matches ? 1.5 : 2));
        renderer.setSize(w, h, false);
        camera.aspect = w / h;
        // Pe desktop scena stă în dreapta textului; pe mobil urcă deasupra lui
        // Pe desktop scena stă în dreapta textului; pe telefon are zona ei și rămâne centrată
        if (small.matches) { camera.clearViewOffset(); }
        else { camera.setViewOffset(w, h, -Math.round(w * 0.2), -Math.round(h * 0.04), w, h); }
        camera.updateProjectionMatrix();
    }
    const ro = new ResizeObserver(resize);
    ro.observe(host);
    ro.observe(canvas);
    resize();

    const outExpo = (x) => (x >= 1 ? 1 : 1 - Math.pow(2, -10 * x));
    const dummy = new THREE.Object3D();
    const target = new THREE.Vector3(0, 0.4, 0);

    function frame(t) {
        // Asamblarea plăcilor, apoi o undă abia vizibilă care pornește din centru
        for (let i = 0; i < meta.length; i++) {
            const m = meta[i];
            const k = outExpo(Math.min(1, Math.max(0, (t - m.delay) / 1.25)));
            const wave = Math.sin(t * 1.3 - m.dist * 0.95) * 0.03 * k;
            dummy.position.set(m.x, (1 - k) * m.drop + wave, m.z);
            dummy.rotation.set((1 - k) * m.spinX, 0, (1 - k) * m.spinZ);
            dummy.scale.setScalar(0.55 + 0.45 * k);
            dummy.updateMatrix();
            tiles.setMatrixAt(i, dummy.matrix);
        }
        tiles.instanceMatrix.needsUpdate = true;

        const grow = outExpo(Math.min(1, Math.max(0, (t - 1.1) / 2.2)));
        lines.forEach((l) => { l.scale.z = Math.max(0.001, grow); });
        belts.forEach((b) => updateBelt(b, t, Math.max(0.02, grow)));

        const p = dust.geometry.attributes.position.array;
        for (let i = 1; i < p.length; i += 3) { p[i] += 0.0035; if (p[i] > 11) { p[i] = 0; } }
        dust.geometry.attributes.position.needsUpdate = true;
        dust.rotation.y = t * 0.012;

        pointer.x += (pointer.tx - pointer.x) * 0.04;
        pointer.y += (pointer.ty - pointer.y) * 0.04;
        const angle = 0.75 + t * 0.055 + pointer.x * 0.22 + scrollP * 0.9;
        const radius = (small.matches ? 21 : 18) + scrollP * 5;
        const height = 8.2 - pointer.y * 0.9 + scrollP * 6;
        camera.position.set(Math.sin(angle) * radius, height, Math.cos(angle) * radius);
        camera.lookAt(target);

        renderer.render(scene, camera);
    }

    if (reduce) {
        frame(30);                       // scena deja asamblată, o singură imagine
        canvas.classList.add('is-ready');
        return;
    }

    let visible = true, running = false, t0 = performance.now(), elapsed = 0;
    function loop(now) {
        if (!visible || document.hidden) { running = false; return; }
        elapsed += Math.min(0.05, (now - t0) / 1000);
        t0 = now;
        frame(elapsed);
        requestAnimationFrame(loop);
    }
    function wake() {
        if (running || !visible || document.hidden) { return; }
        running = true; t0 = performance.now();
        requestAnimationFrame(loop);
    }
    new IntersectionObserver((entries) => { visible = entries[0].isIntersecting; wake(); }, { threshold: 0.01 }).observe(host);
    document.addEventListener('visibilitychange', wake);
    frame(0);
    canvas.classList.add('is-ready');
    wake();
}
