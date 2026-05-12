(() => {
  const canvas = document.querySelector('[data-hero-particles]');
  if (!canvas || !window.EZMotion) return;

  const hero = canvas.closest('.hero') || canvas;
  const M = window.EZMotion;
  const THREE = window.THREE;

  if (THREE && !M.prefersReduced()) {
    try {
      initThreeParticles();
      return;
    } catch (error) {
      console.warn('[hero-particles] Three.js mode failed, fallback enabled:', error);
      initCanvasFallback();
      return;
    }
  }

  initCanvasFallback();

  function initThreeParticles() {
    const CONFIG = {
      particlesHigh: 50000,
      particlesMedium: 26000,
      particlesLow: 9000,

      cameraWidth: 360,
      cameraHeight: 240,

      // Инверсия камеры:
      // true  = тёмные места камеры становятся яркими точками.
      // false = светлые места камеры становятся яркими точками.
      invertCamera: true,

      // Для инверсии:
      // Если слишком много белого шума — подними до 0.25–0.35.
      // Если лицо/тёмные детали пропадают — опусти до 0.12–0.18.
      brightnessThreshold: 0.18,
      brightnessContrast: 2.8,

      // Общая яркость в режиме камеры.
      cameraOpacityDark: 0.58,
      cameraOpacityLight: 0.38,

      // Размер точек.
      pointBaseSizeDesktop: 2.55,
      pointBaseSizeMobile: 3.6,

      // Растяжение камеры по hero.
      cameraScaleX: 2.35,
      cameraScaleY: 2.05,

      // Движение частиц в режиме камеры слева направо.
      cameraFlowSpeed: 0.055,

      // Насколько сильно частицы в камере дрожат/текут.
      cameraWave: 0.035,
    };

    const renderer = new THREE.WebGLRenderer({
      canvas,
      alpha: true,
      antialias: true,
      powerPreference: 'high-performance',
    });

    const scene = new THREE.Scene();
    const camera = new THREE.OrthographicCamera(-1, 1, 1, -1, -10, 10);
    const clock = new THREE.Clock();

    let width = 1;
    let height = 1;
    let aspect = 1;
    let frame = null;
    let visible = true;
    let pointMesh = null;
    let pointMeta = [];
    let lastScrollY = window.scrollY;
    let scrollVelocity = 0;

    let cameraVideo = null;
    let cameraStream = null;
    let cameraPixels = null;
    let cameraFrame = 0;
    let cameraAllowed = false;
    let cameraHasUsefulImage = false;
    let cameraStarting = false;

    const cameraSample = {
      canvas: document.createElement('canvas'),
      width: CONFIG.cameraWidth,
      height: CONFIG.cameraHeight,
      ctx: null,
    };

    const pointer = {
      x: 0,
      y: 0,
      tx: 0,
      ty: 0,
      active: false,
      strength: 0,
    };

    const qualityPreset = () => {
      const level = M.motionLevel();

      if (level === 'low') {
        return { particles: CONFIG.particlesLow, dpr: 1, speed: 0.78 };
      }

      if (level === 'medium') {
        return { particles: CONFIG.particlesMedium, dpr: 1.1, speed: 0.9 };
      }

      return { particles: CONFIG.particlesHigh, dpr: 1.15, speed: 1 };
    };

    const particleColor = () => (M.isLightTheme() ? 0x5f625f : 0xffffff);

    function updateScrollVelocity() {
      const current = window.scrollY;
      const diff = current - lastScrollY;

      lastScrollY = current;
      scrollVelocity = M.lerp(scrollVelocity, M.clamp(diff, -80, 80), 0.08);
    }

    function makeMeta(count) {
      const points = [];

      const videoCols = Math.ceil(Math.sqrt(count * (cameraSample.width / cameraSample.height)));
      const videoRows = Math.ceil(count / videoCols);

      for (let index = 0; index < count; index += 1) {
        const family = index % 9;
        const band = Math.random() * 2 - 1;
        const seed = Math.random() * 1000;
        const phase = seed * 0.017;
        const amplitude = 0.018 + Math.random() * 0.12;
        const flow = 0.42 + Math.random() * 1.65;
        const lane = band * (0.16 + Math.random() * 0.94);

        const size = Math.random() > 0.9
          ? 1.55 + Math.random() * 0.9
          : 0.75 + Math.random() * 0.75;

        const depth = Math.random();

        const cellX = index % videoCols;
        const cellY = Math.floor(index / videoCols);

        const imageU = (cellX + Math.random() * 0.95) / videoCols;
        const imageV = (cellY + Math.random() * 0.95) / videoRows;

        points.push({
          u: Math.random(),
          band,
          lane,
          family,
          phase,
          amplitude,
          flow,
          seed,
          size,
          depth,
          imageU,
          imageV,
          faceHold: 0,
        });
      }

      return points;
    }

    function updateCameraFrame() {
      if (!visible || !cameraAllowed || !cameraVideo || cameraVideo.readyState < 2 || !cameraSample.ctx) return;

      cameraFrame += 1;

      if (cameraFrame % 2 !== 0 && cameraPixels) return;

      const ctx = cameraSample.ctx;

      ctx.save();
      ctx.clearRect(0, 0, cameraSample.width, cameraSample.height);
      ctx.translate(cameraSample.width, 0);
      ctx.scale(-1, 1);
      ctx.drawImage(cameraVideo, 0, 0, cameraSample.width, cameraSample.height);
      ctx.restore();

      cameraPixels = ctx.getImageData(0, 0, cameraSample.width, cameraSample.height).data;
      cameraHasUsefulImage = detectUsefulCameraImage(cameraPixels);
    }

    function detectUsefulCameraImage(pixels) {
      if (!pixels) return false;

      let usable = 0;
      let contrast = 0;
      let last = null;
      const step = 4 * 23;

      for (let i = 0; i < pixels.length; i += step) {
        const r = pixels[i] || 0;
        const g = pixels[i + 1] || 0;
        const b = pixels[i + 2] || 0;
        const light = (r * 0.299 + g * 0.587 + b * 0.114) / 255;

        if (light > 0.18) usable += 1;
        if (last !== null) contrast += Math.abs(light - last);

        last = light;
      }

      return usable > 18 && contrast > 3.2;
    }

    function cameraBrightnessAt(u, v) {
      if (!cameraPixels || !cameraHasUsefulImage) return 0;

      const wrappedU = ((u % 1) + 1) % 1;
      const clampedV = M.clamp(v, 0, 1);

      const x = Math.min(
        cameraSample.width - 1,
        Math.max(0, Math.floor(wrappedU * cameraSample.width))
      );

      const y = Math.min(
        cameraSample.height - 1,
        Math.max(0, Math.floor(clampedV * cameraSample.height))
      );

      const index = (y * cameraSample.width + x) * 4;

      const r = cameraPixels[index] || 0;
      const g = cameraPixels[index + 1] || 0;
      const b = cameraPixels[index + 2] || 0;

      let light = (r * 0.299 + g * 0.587 + b * 0.114) / 255;

      // Инверсия: тёмное становится светлым, светлое становится тёмным.
      if (CONFIG.invertCamera) {
        light = 1 - light;
      }

      const value = (light - CONFIG.brightnessThreshold) * CONFIG.brightnessContrast;

      return M.clamp(value, 0, 1);
    }

    function applyPointer(x, y, time, meta) {
      let warpedX = x;
      let warpedY = y;

      if (Math.abs(scrollVelocity) > 0.1) {
        warpedX += scrollVelocity * 0.0028 * Math.sin(meta.phase + meta.u * Math.PI);
        warpedY -= scrollVelocity * 0.0012;
      }

      if (pointer.strength > 0.01) {
        const dx = warpedX - pointer.x;
        const dy = warpedY - pointer.y;
        const dist = Math.sqrt(dx * dx + dy * dy) || 1;
        const radius = width < 760 ? 0.44 : 0.72;
        const force = Math.max(0, 1 - dist / radius);
        const wake = Math.sin(dist * 24 - time * 4.4 + meta.phase) * force * pointer.strength;

        warpedX += (dx / dist) * force * force * 0.32 * pointer.strength + wake * 0.055;
        warpedY += (dy / dist) * force * force * 0.25 * pointer.strength
          + Math.cos(dist * 18 - time * 3.4) * force * 0.05 * pointer.strength;
      }

      return { x: warpedX, y: warpedY };
    }

    function sample(meta, time) {
      const cameraMode = cameraAllowed && cameraHasUsefulImage;

      if (cameraMode) {
        const flowU = (meta.imageU + time * CONFIG.cameraFlowSpeed * meta.flow) % 1;
        const flowV = meta.imageV;

        const videoLight = cameraBrightnessAt(flowU, flowV);

        const lerpAmount = videoLight > meta.faceHold ? 0.32 : 0.10;
        meta.faceHold = M.lerp(meta.faceHold || 0, videoLight, lerpAmount);

        const heldLight = meta.faceHold;

        const xBase = (flowU - 0.5) * aspect * CONFIG.cameraScaleX;
        const yBase = (0.5 - flowV) * CONFIG.cameraScaleY;

        const wave = Math.sin(flowU * 18 + time * 0.9 + meta.phase)
          * CONFIG.cameraWave
          * (0.25 + heldLight);

        const drift = Math.cos(time * 0.42 + meta.seed) * 0.006;

        const pointered = applyPointer(xBase, yBase + wave + drift, time, meta);

        if (heldLight > 0.022) {
          return {
            x: pointered.x,
            y: pointered.y,
            z: (heldLight - 0.5) * 0.06 + (meta.depth - 0.5) * 0.02,
            alpha: M.clamp(heldLight * 0.76, 0.035, 0.65),
            size: meta.size * (0.58 + heldLight * 0.48),
          };
        }

        return {
          x: pointered.x,
          y: pointered.y,
          z: -0.08,
          alpha: 0.006,
          size: meta.size * 0.2,
        };
      }

      const stream = (meta.u + time * 0.035 * meta.flow + meta.phase * 0.011) % 1;
      const u = stream;
      const band = meta.band;
      const t = time * meta.flow;

      const spreadX = aspect * 1.55;
      const wakeEdge = Math.sin(u * Math.PI);

      const weave = Math.sin(u * 22 + t + meta.phase) * meta.amplitude;
      const ripple = Math.cos(u * 13.5 - t * 0.72 + meta.phase + band * 2.4) * meta.amplitude * 0.82;
      const curl = Math.sin((u * 4.2 + band * 1.8) * Math.PI + t * 0.4 + meta.seed) * 0.16 * wakeEdge;
      const braid = Math.sin(u * Math.PI * (meta.family + 2) + meta.phase) * 0.09 * wakeEdge;

      const waveOffset = Math.sin(time * 0.16 + band * 2.2 + meta.phase) * 0.16;

      let x = -spreadX + u * spreadX * 2;
      let y = meta.lane * 0.72 + weave + ripple + curl + braid + waveOffset;

      if (meta.family === 1) y += (u - 0.5) * 0.22;
      if (meta.family === 2) y -= (u - 0.5) * 0.20;
      if (meta.family === 3) y += Math.sin(u * Math.PI * 2 + t * 0.35 + meta.phase) * 0.18;
      if (meta.family === 4) y += Math.cos(u * Math.PI * 3 - t * 0.5 + meta.phase) * 0.16;
      if (meta.family === 5) y += Math.sin(u * Math.PI * 5 + t * 0.18 + meta.phase) * 0.11;
      if (meta.family === 6) y -= Math.cos(u * Math.PI * 4 - t * 0.28 + meta.phase) * 0.10;

      let warpedX = x + Math.sin(y * 5 + t * 0.28 + meta.phase) * 0.09 * wakeEdge;
      let warpedY = y;

      const fold = Math.sin(warpedX * 2.2 + time * 0.42 + meta.phase)
        * Math.cos(warpedY * 3.6 - time * 0.34);

      warpedX += fold * 0.09;
      warpedY += fold * 0.11;

      const pointered = applyPointer(warpedX, warpedY, time, meta);

      return {
        x: pointered.x,
        y: pointered.y,
        z: fold * 0.14 + (meta.depth - 0.5) * 0.08,
        alpha: 0.07 + meta.depth * 0.38,
        size: meta.size * 0.74,
      };
    }

    function rebuild() {
      const preset = qualityPreset();
      const rect = canvas.getBoundingClientRect();

      width = Math.max(1, rect.width);
      height = Math.max(1, rect.height);
      aspect = width / height;

      renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, preset.dpr));
      renderer.setSize(width, height, false);
      renderer.setClearColor(0x000000, 0);

      camera.left = -aspect;
      camera.right = aspect;
      camera.top = 1;
      camera.bottom = -1;
      camera.updateProjectionMatrix();

      if (pointMesh) {
        pointMesh.geometry.dispose();
        pointMesh.material.dispose();
        scene.remove(pointMesh);
      }

      pointMeta = makeMeta(preset.particles);

      const pointGeometry = new THREE.BufferGeometry();

      pointGeometry.setAttribute(
        'position',
        new THREE.BufferAttribute(new Float32Array(pointMeta.length * 3), 3)
      );

      pointGeometry.setAttribute(
        'particleSize',
        new THREE.BufferAttribute(new Float32Array(pointMeta.map((point) => point.size)), 1)
      );

      pointGeometry.setAttribute(
        'particleAlpha',
        new THREE.BufferAttribute(new Float32Array(pointMeta.map(() => 0.08)), 1)
      );

      const pointMaterial = new THREE.ShaderMaterial({
        transparent: true,
        blending: THREE.AdditiveBlending,
        depthWrite: false,
        uniforms: {
          uColor: { value: new THREE.Color(particleColor()) },
          uBaseSize: {
            value: width < 760
              ? CONFIG.pointBaseSizeMobile
              : CONFIG.pointBaseSizeDesktop,
          },
          uOpacity: {
            value: M.isLightTheme()
              ? CONFIG.cameraOpacityLight
              : CONFIG.cameraOpacityDark,
          },
        },
        vertexShader: `
          attribute float particleSize;
          attribute float particleAlpha;

          uniform float uBaseSize;

          varying float vAlpha;

          void main() {
            vAlpha = particleAlpha;

            vec4 mvPosition = modelViewMatrix * vec4(position, 1.0);

            gl_Position = projectionMatrix * mvPosition;
            gl_PointSize = particleSize * uBaseSize;
          }
        `,
        fragmentShader: `
          uniform vec3 uColor;
          uniform float uOpacity;

          varying float vAlpha;

          void main() {
            vec2 coord = gl_PointCoord - vec2(0.5);
            float dist = length(coord);

            float core = 1.0 - smoothstep(0.10, 0.36, dist);
            float halo = 1.0 - smoothstep(0.26, 0.50, dist);

            float alpha = max(core * 0.72, halo * 0.10) * uOpacity * vAlpha;

            if (alpha < 0.008) discard;

            gl_FragColor = vec4(uColor, alpha);
          }
        `,
      });

      pointMesh = new THREE.Points(pointGeometry, pointMaterial);
      scene.add(pointMesh);
    }

    function updateGeometry(time) {
      pointer.x = M.lerp(pointer.x, pointer.tx, 0.11);
      pointer.y = M.lerp(pointer.y, pointer.ty, 0.11);
      pointer.strength = M.lerp(pointer.strength, pointer.active ? 1 : 0, 0.055);

      updateCameraFrame();

      const pointPositions = pointMesh.geometry.attributes.position.array;
      const pointSizes = pointMesh.geometry.attributes.particleSize.array;
      const pointAlphas = pointMesh.geometry.attributes.particleAlpha.array;

      for (let i = 0; i < pointMeta.length; i += 1) {
        const p = sample(pointMeta[i], time);
        const offset = i * 3;

        pointPositions[offset] = p.x;
        pointPositions[offset + 1] = p.y;
        pointPositions[offset + 2] = p.z;

        pointSizes[i] = p.size;
        pointAlphas[i] = p.alpha;
      }

      pointMesh.geometry.attributes.position.needsUpdate = true;
      pointMesh.geometry.attributes.particleSize.needsUpdate = true;
      pointMesh.geometry.attributes.particleAlpha.needsUpdate = true;
    }

    function render() {
      if (!visible) {
        frame = requestAnimationFrame(render);
        return;
      }

      updateScrollVelocity();

      const preset = qualityPreset();
      const time = clock.getElapsedTime() * preset.speed;

      pointMesh.material.uniforms.uColor.value.setHex(particleColor());
      pointMesh.material.uniforms.uOpacity.value = M.isLightTheme()
        ? CONFIG.cameraOpacityLight
        : CONFIG.cameraOpacityDark;

      updateGeometry(time);
      renderer.render(scene, camera);

      scrollVelocity *= 0.92;
      frame = requestAnimationFrame(render);
    }

    function setPointer(event) {
      const rect = canvas.getBoundingClientRect();

      pointer.tx = ((event.clientX - rect.left) / rect.width - 0.5) * 2 * aspect;
      pointer.ty = -(((event.clientY - rect.top) / rect.height - 0.5) * 2);
      pointer.active = true;
    }

    function setPointerFromWindow(event) {
      const heroRect = hero.getBoundingClientRect();

      const isInsideHero = event.clientX >= heroRect.left
        && event.clientX <= heroRect.right
        && event.clientY >= heroRect.top
        && event.clientY <= heroRect.bottom;

      if (!isInsideHero) {
        pointer.active = false;
        return;
      }

      setPointer(event);
    }

    function start() {
      if (frame) cancelAnimationFrame(frame);
      frame = requestAnimationFrame(render);
    }

    M.createVisibilityController(canvas, (isVisible) => {
      visible = isVisible;
      if (visible && !document.hidden) {
        startCamera();
      } else {
        stopCamera();
      }
    });

    window.addEventListener('resize', rebuild, { passive: true });
    window.addEventListener('scroll', updateScrollVelocity, { passive: true });
    window.addEventListener('pointermove', setPointerFromWindow, { passive: true });

    hero.addEventListener('pointerleave', () => {
      pointer.active = false;
    }, { passive: true });

    rebuild();
    start();
    startCamera();

    document.addEventListener('visibilitychange', () => {
      if (document.hidden) {
        if (frame) cancelAnimationFrame(frame);
        stopCamera();
      }

      if (!document.hidden) {
        start();
        if (visible) startCamera();
      }
    });

    window.addEventListener('pagehide', stopCamera, { passive: true });

    async function startCamera() {
      if (cameraAllowed || cameraStarting) return;
      if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) return;

      cameraSample.canvas.width = cameraSample.width;
      cameraSample.canvas.height = cameraSample.height;
      cameraSample.ctx = cameraSample.canvas.getContext('2d', { willReadFrequently: true });

      if (!cameraSample.ctx) return;

      cameraStarting = true;

      try {
        const stream = await navigator.mediaDevices.getUserMedia({
          audio: false,
          video: {
            width: { ideal: 1280 },
            height: { ideal: 720 },
            facingMode: 'user',
          },
        });

        if (!visible || document.hidden) {
          stream.getTracks().forEach((track) => track.stop());
          return;
        }

        cameraStream = stream;
        cameraVideo = document.createElement('video');
        cameraVideo.muted = true;
        cameraVideo.autoplay = true;
        cameraVideo.playsInline = true;
        cameraVideo.srcObject = cameraStream;

        await cameraVideo.play();

        cameraAllowed = true;
      } catch (error) {
        cameraAllowed = false;
      } finally {
        cameraStarting = false;
      }
    }

    function stopCamera() {
      if (cameraStream) {
        cameraStream.getTracks().forEach((track) => track.stop());
      }

      if (cameraVideo) {
        cameraVideo.pause();
        cameraVideo.srcObject = null;
      }

      cameraStream = null;
      cameraVideo = null;
      cameraPixels = null;
      cameraFrame = 0;
      cameraAllowed = false;
      cameraHasUsefulImage = false;
    }
  }

  function initCanvasFallback() {
    let ctx;
    let width = 1;
    let height = 1;
    let cols = 0;
    let rows = 0;
    let points = [];
    let frame = null;
    let time = Math.random() * 100;
    let visible = true;
    let lastScrollY = window.scrollY;
    let scrollVelocity = 0;

    const pointer = {
      x: 0,
      y: 0,
      tx: 0,
      ty: 0,
      active: false,
      strength: 0,
    };

    const qualityPreset = () => {
      const level = M.motionLevel();
      if (level === 'reduced') return { density: 0.28, speed: 0.35, dpr: 1 };
      if (level === 'low') return { density: 0.55, speed: 0.75, dpr: 1.35 };
      if (level === 'medium') return { density: 0.82, speed: 0.88, dpr: 1.6 };
      return { density: 1, speed: 1, dpr: 2 };
    };

    function particleInk(alpha) {
      return M.isLightTheme()
        ? `rgba(72, 75, 72, ${alpha})`
        : `rgba(255, 255, 255, ${alpha})`;
    }

    function resize() {
      const preset = qualityPreset();
      const fit = M.setupCanvas(canvas, preset.dpr);

      ctx = fit.ctx;
      width = fit.width;
      height = fit.height;

      cols = Math.floor(M.clamp(width / 25, 26, 66) * preset.density);
      rows = Math.floor(M.clamp(height / 22, 20, 48) * preset.density);

      cols = Math.max(12, cols);
      rows = Math.max(10, rows);
      points = [];

      const xStart = width * (width < 760 ? 0.02 : 0.14);
      const xEnd = width * 1.08;
      const yStart = height * 0.08;
      const yEnd = height * 0.94;

      for (let y = 0; y < rows; y += 1) {
        for (let x = 0; x < cols; x += 1) {
          const u = cols <= 1 ? 0 : x / (cols - 1);
          const v = rows <= 1 ? 0 : y / (rows - 1);
          const focus = Math.sin(u * Math.PI) * Math.sin(v * Math.PI);

          points.push({
            u,
            v,
            focus,
            bx: xStart + (xEnd - xStart) * u,
            by: yStart + (yEnd - yStart) * v,
            seed: Math.random() * 900 + x * 0.3 + y * 0.7,
          });
        }
      }
    }

    function updateScrollVelocity() {
      const current = window.scrollY;
      const diff = current - lastScrollY;

      lastScrollY = current;
      scrollVelocity = M.lerp(scrollVelocity, M.clamp(diff, -70, 70), 0.08);
    }

    function project(point) {
      const preset = qualityPreset();

      const u = point.u;
      const v = point.v;
      const focus = point.focus;
      const t = time * preset.speed;

      const depth = 0.55 + Math.sin(u * Math.PI) * 0.32
        + Math.cos(v * Math.PI * 2 + t * 0.23) * 0.06;

      const perspective = M.map(depth, 0.35, 1, 0.84, 1.15);
      const centerX = width * 0.56;
      const centerY = height * 0.48;

      const waveA = Math.sin(u * 10.5 + t * 0.48 + point.seed * 0.019);
      const waveB = Math.cos(v * 12.2 + t * 0.42 + point.seed * 0.013);
      const waveC = Math.sin((u + v) * 15.8 + t * 0.31 + point.seed * 0.006);

      const drift = Math.sin(t * 0.18 + point.seed * 0.01) * focus;

      let x = point.bx + waveA * 34 + waveB * 16 + drift * 58;
      let y = point.by + waveB * 28 + waveC * 19 + Math.cos(t * 0.2 + u * 8) * focus * 34;

      x = centerX + (x - centerX) * perspective;
      y = centerY + (y - centerY) * (0.88 + perspective * 0.12);

      if (Math.abs(scrollVelocity) > 0.1) {
        x += scrollVelocity * focus * 0.52;
        y -= scrollVelocity * (0.04 + v * 0.12);
      }

      if (M.hasFinePointer()) {
        pointer.x = M.lerp(pointer.x, pointer.tx, 0.12);
        pointer.y = M.lerp(pointer.y, pointer.ty, 0.12);
        pointer.strength = M.lerp(pointer.strength, pointer.active ? 1 : 0, 0.055);

        if (pointer.strength > 0.01) {
          const dx = x - pointer.x;
          const dy = y - pointer.y;
          const dist = Math.sqrt(dx * dx + dy * dy) || 1;
          const radius = width < 760 ? 190 : 360;

          if (dist < radius) {
            const force = Math.pow((radius - dist) / radius, 2) * pointer.strength;
            const push = 150 + Math.abs(scrollVelocity) * 0.9;

            x += (dx / dist) * force * push;
            y += (dy / dist) * force * 105;
          }

          const wake = Math.sin(dist * 0.018 - time * 2.1 + point.seed * 0.01) * pointer.strength;
          const falloff = Math.max(0, 1 - dist / Math.max(width, height));

          x += wake * falloff * focus * 18;
          y += Math.cos(dist * 0.014 - time * 1.7) * falloff * focus * 14 * pointer.strength;
        }
      }

      return { x, y, focus, depth };
    }

    function draw() {
      if (!visible) {
        frame = requestAnimationFrame(draw);
        return;
      }

      updateScrollVelocity();
      ctx.clearRect(0, 0, width, height);

      const level = M.motionLevel();
      const reduced = level === 'reduced';

      const glow = ctx.createRadialGradient(
        width * 0.55,
        height * 0.45,
        0,
        width * 0.55,
        height * 0.45,
        Math.max(width, height) * 0.62
      );

      glow.addColorStop(0, particleInk(M.isLightTheme() ? 0.09 : 0.08));
      glow.addColorStop(1, 'rgba(0,0,0,0)');

      ctx.fillStyle = glow;
      ctx.fillRect(0, 0, width, height);

      const mapped = points.map(project);

      ctx.save();
      ctx.globalCompositeOperation = 'lighter';

      for (let y = 0; y < rows; y += 1) {
        ctx.beginPath();

        for (let x = 0; x < cols; x += 1) {
          const p = mapped[y * cols + x];

          if (x === 0) ctx.moveTo(p.x, p.y);
          else ctx.lineTo(p.x, p.y);
        }

        const isGuide = y % 6 === 0;

        ctx.strokeStyle = isGuide ? particleInk(0.18) : particleInk(0.08);
        ctx.lineWidth = isGuide ? 1.1 : 0.6;
        ctx.globalAlpha = isGuide ? 0.36 : 0.22;
        ctx.stroke();
      }

      for (let x = 0; x < cols; x += 3) {
        ctx.beginPath();

        for (let y = 0; y < rows; y += 1) {
          const p = mapped[y * cols + x];

          if (y === 0) ctx.moveTo(p.x, p.y);
          else ctx.lineTo(p.x, p.y);
        }

        ctx.strokeStyle = particleInk(0.07);
        ctx.lineWidth = 0.55;
        ctx.globalAlpha = 0.18;
        ctx.stroke();
      }

      ctx.fillStyle = particleInk(0.5);

      for (let i = 0; i < mapped.length; i += level === 'low' ? 11 : 7) {
        const p = mapped[i];
        const pulse = (Math.sin(time * 0.8 + i * 0.35) + 1) * 0.5;

        if (pulse < 0.34) continue;

        ctx.globalAlpha = 0.10 + pulse * 0.26;
        ctx.beginPath();
        ctx.arc(p.x, p.y, 0.4 + pulse * 0.75, 0, Math.PI * 2);
        ctx.fill();
      }

      ctx.restore();

      scrollVelocity *= 0.92;
      time += reduced ? 0.003 : 0.016;

      frame = requestAnimationFrame(draw);
    }

    function setPointer(event) {
      const rect = canvas.getBoundingClientRect();

      pointer.tx = event.clientX - rect.left;
      pointer.ty = event.clientY - rect.top;
      pointer.active = true;
    }

    function setPointerFromWindow(event) {
      const heroRect = hero.getBoundingClientRect();

      const isInsideHero = event.clientX >= heroRect.left
        && event.clientX <= heroRect.right
        && event.clientY >= heroRect.top
        && event.clientY <= heroRect.bottom;

      if (!isInsideHero) {
        pointer.active = false;
        return;
      }

      setPointer(event);
    }

    function start() {
      if (frame) cancelAnimationFrame(frame);
      frame = requestAnimationFrame(draw);
    }

    M.createVisibilityController(canvas, (isVisible) => {
      visible = isVisible;
    });

    window.addEventListener('resize', resize, { passive: true });
    window.addEventListener('scroll', updateScrollVelocity, { passive: true });
    window.addEventListener('pointermove', setPointerFromWindow, { passive: true });

    hero.addEventListener('pointerleave', () => {
      pointer.active = false;
    }, { passive: true });

    resize();
    start();

    document.addEventListener('visibilitychange', () => {
      if (document.hidden && frame) cancelAnimationFrame(frame);
      if (!document.hidden) start();
    });
  }
})();
