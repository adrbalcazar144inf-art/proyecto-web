// editor.js

document.addEventListener('DOMContentLoaded', () => {
  const canvas = new fabric.Canvas('editor-canvas', {
    isDrawingMode: false,
    backgroundColor: '#000'
  });

  const history = { undoStack: [], redoStack: [] };
  let drawing = false, isCropping = false, isErasing = false, cropPath;

  const $ = id => document.getElementById(id);
  const colorPicker    = $('color-picker'),
        sizeSlider     = $('size-slider'),
        cropTolerance  = $('crop-tolerance');

  // Simple debounce
  const debounce = (fn, t = 100) => {
    let tm;
    return (...args) => {
      clearTimeout(tm);
      tm = setTimeout(() => fn(...args), t);
    };
  };

  // ─── Historial ──────────────────────────────────────────────────────────────
  function saveState() {
    const j = canvas.toJSON(['selectable']);
    const last = history.undoStack.at(-1);
    if (JSON.stringify(j) !== JSON.stringify(last)) {
      history.undoStack.push(j);
      if (history.undoStack.length > 50) history.undoStack.shift();
      history.redoStack = [];
    }
  }
  function loadState(state) {
    canvas.loadFromJSON(state, () => canvas.renderAll());
  }
  saveState();

  // ─── Utilidades ─────────────────────────────────────────────────────────────
  function deactivateAllModes() {
    drawing = isErasing = isCropping = canvas.isDrawingMode = false;
    ['draw-mode','eraser-btn','free-crop-btn'].forEach(id =>
      $(id).classList.remove('active')
    );
  }
  function updatePropertiesPanel(obj) {
    ['prop-color','prop-opacity','prop-scale','prop-rotation'].forEach(id => {
      const el = $(id);
      el.disabled = !obj;
      if (!obj) {
        if (id === 'prop-color')    el.value = '#000000';
        if (id === 'prop-opacity')  el.value = 1;
        if (id === 'prop-scale')    el.value = 100;
        if (id === 'prop-rotation') el.value = 0;
      }
    });
  }

  // ─── Eventos de canvas ──────────────────────────────────────────────────────
  canvas.on('object:added',   debounce(() => { saveState(); refreshLayers(); }));
  canvas.on('object:modified',debounce(() => { saveState(); refreshLayers(); }));
  canvas.on('object:removed', debounce(() => { saveState(); refreshLayers(); }));

  canvas.on('after:render', () => {
    canvas.getObjects('line').forEach(l => canvas.remove(l));
    drawGrid();
  });

  // ─── Modo dibujo ─────────────────────────────────────────────────────────────
  $('draw-mode').onclick = () => {
    deactivateAllModes();
    if (!drawing) {
      drawing = true;
      canvas.isDrawingMode = true;
      canvas.freeDrawingBrush = new fabric.PencilBrush(canvas);
      canvas.freeDrawingBrush.color = colorPicker.value;
      canvas.freeDrawingBrush.width = +sizeSlider.value;
      $('draw-mode').classList.add('active');
    }
  };
  colorPicker.onchange  = () => canvas.freeDrawingBrush && (canvas.freeDrawingBrush.color = colorPicker.value);
  sizeSlider.oninput    = () => canvas.freeDrawingBrush && (canvas.freeDrawingBrush.width = +sizeSlider.value);

  // ─── Borrador ───────────────────────────────────────────────────────────────
  $('eraser-btn').onclick = () => {
    deactivateAllModes();
    isErasing = true;
    canvas.isDrawingMode = true;
    canvas.freeDrawingBrush = new fabric.EraserBrush(canvas);
    canvas.freeDrawingBrush.width = +sizeSlider.value;
    $('eraser-btn').classList.add('active');
  };

  // ─── Texto ─────────────────────────────────────────────────────────────────
  $('add-text').onclick = () => {
    canvas.add(new fabric.IText('Escribe aquí', {
      left: 100, top: 100,
      fill: colorPicker.value, fontSize: 30
    }));
    saveState();
  };

  // ─── Carga de imagen (versión test) ─────────────────────────────────────────
  $('upload-image').onchange = e => {
    const file = e.target.files[0];
    if (!file) return alert('No hay archivo seleccionado');

    const reader = new FileReader();
    reader.onerror = () => alert('Error al leer el archivo');
    reader.onload = evt => {
      // Limpiar todo
      canvas.clear();
      drawGrid();

      // Cargar con Fabric
      fabric.Image.fromURL(evt.target.result, img => {
        if (!img) return alert('No se pudo crear la imagen');

        img.scaleToWidth(canvas.getWidth());
        img.scaleToHeight(canvas.getHeight());
        img.set({ left: 0, top: 0 });

        canvas.add(img);
        canvas.requestRenderAll();
        saveState();
        refreshLayers();
      }, { crossOrigin: 'anonymous' });
    };
    reader.readAsDataURL(file);
  };

  // ─── Filtros ────────────────────────────────────────────────────────────────
  $('filter-select').onchange = e => {
    const obj = canvas.getActiveObject();
    if (!obj || obj.type !== 'image') return alert('Selecciona una imagen');
    const map = {
      grayscale: new fabric.Image.filters.Grayscale(),
      invert:    new fabric.Image.filters.Invert(),
      sepia:     new fabric.Image.filters.Sepia(),
      brightness:new fabric.Image.filters.Brightness({ brightness: 0.2 })
    };
    obj.filters = [ map[e.target.value] ].filter(Boolean);
    obj.applyFilters();
    canvas.renderAll();
  };

  // ─── Quitar fondo ───────────────────────────────────────────────────────────
  $('remove-bg-btn').onclick = () => {
    const obj = canvas.getActiveObject();
    if (!obj || obj.type !== 'image') return alert('Selecciona una imagen');
    const el = obj.getElement(), tmp = document.createElement('canvas');
    tmp.width = el.width; tmp.height = el.height;
    const ctx = tmp.getContext('2d');
    ctx.drawImage(el, 0, 0);
    const data = ctx.getImageData(0, 0, tmp.width, tmp.height);
    const tol = +cropTolerance.value;
    for (let i = 0; i < data.data.length; i += 4) {
      if (data.data[i] > tol && data.data[i+1] > tol && data.data[i+2] > tol)
        data.data[i+3] = 0;
    }
    ctx.putImageData(data, 0, 0);
    fabric.Image.fromURL(tmp.toDataURL(), img => {
      img.set({
        left: obj.left, top: obj.top,
        scaleX: obj.scaleX, scaleY: obj.scaleY,
        angle: obj.angle
      });
      canvas.remove(obj).add(img).setActiveObject(img).renderAll();
      saveState();
    });
  };

  // ─── Recorte libre ──────────────────────────────────────────────────────────
  $('free-crop-btn').onclick = () => {
    const obj = canvas.getActiveObject();
    if (!obj || obj.type !== 'image') return alert('Selecciona una imagen');
    deactivateAllModes();
    isCropping = true;
    canvas.isDrawingMode = true;
    const brush = new fabric.PencilBrush(canvas);
    brush.color = '#0ff'; brush.width = 2;
    canvas.freeDrawingBrush = brush;
    $('free-crop-btn').classList.add('active');
  };
  canvas.on('path:created', opt => {
    if (!isCropping) return;
    cropPath = opt.path;
    cropPath.selectable = cropPath.evented = false;
    const img = canvas.getActiveObject();
    const clipped = new fabric.Image(img.getElement(), {
      left: img.left, top: img.top,
      scaleX: img.scaleX, scaleY: img.scaleY,
      angle: img.angle, clipPath: cropPath
    });
    canvas.remove(img).add(clipped);
    isCropping = false; cropPath = null;
    saveState(); refreshLayers();
  });

  // ─── Stickers ──────────────────────────────────────────────────────────────
  $('stickers-select').onchange = e => {
    if (!e.target.value) return;
    fabric.Image.fromURL(e.target.value, img => {
      img.set({ left: canvas.width/2 - 50, top: canvas.height/2 - 50 });
      img.scaleToWidth(100);
      canvas.add(img).setActiveObject(img).renderAll();
      saveState(); refreshLayers();
      e.target.value = '';
    });
  };

  // ─── Propiedades de objeto ─────────────────────────────────────────────────
  ['selection:created','selection:updated'].forEach(evt =>
    canvas.on(evt, e => updatePropertiesPanel(e.target))
  );
  canvas.on('selection:cleared', () => updatePropertiesPanel(null));
  $('prop-color').onchange    = () => { const o = canvas.getActiveObject(); if (o) o.set('fill', $('prop-color').value); canvas.requestRenderAll(); saveState(); };
  $('prop-opacity').oninput   = () => { const o = canvas.getActiveObject(); if (o) o.set('opacity', +$('prop-opacity').value); canvas.requestRenderAll(); saveState(); };
  $('prop-scale').onchange    = () => { const o = canvas.getActiveObject(); if (o) { const s = +$('prop-scale').value / 100; o.scaleX = o.scaleY = s; canvas.requestRenderAll(); saveState(); } };
  $('prop-rotation').onchange = () => { const o = canvas.getActiveObject(); if (o) o.angle = +$('prop-rotation').value; canvas.requestRenderAll(); saveState(); };

  // ─── Undo/Redo ─────────────────────────────────────────────────────────────
  $('undo-btn').onclick = () => {
    if (history.undoStack.length > 1) {
      history.redoStack.push(history.undoStack.pop());
      canvas._loading = true;
      loadState(history.undoStack.at(-1));
      canvas._loading = false;
    }
  };
  $('redo-btn').onclick = () => {
    if (history.redoStack.length) {
      const nxt = history.redoStack.pop();
      history.undoStack.push(nxt);
      canvas._loading = true;
      loadState(nxt);
      canvas._loading = false;
    }
  };
  document.addEventListener('keydown', e => {
    if (e.ctrlKey && e.key === 'z') $('undo-btn').click();
    if (e.ctrlKey && e.key === 'y') $('redo-btn').click();
  });

  // ─── Exportar ──────────────────────────────────────────────────────────────
  $('export-img').onclick = () => {
    const link = document.createElement('a');
    link.download = 'imagen_editada.png';
    link.href = canvas.toDataURL({ format: 'png' });
    link.click();
  };

  // ─── Panel de capas ─────────────────────────────────────────────────────────
  function refreshLayers() {
    const list = $('layers-list');
    list.innerHTML = '';
    canvas.getObjects().slice().reverse().forEach(obj => {
      const li = document.createElement('li');
      li.className = 'list-group-item';
      li.textContent = `${obj.type} ${canvas.getObjects().indexOf(obj)}`;

      const toggle = document.createElement('button');
      toggle.textContent = obj.visible ? '👁' : '🚫';
      toggle.onclick = () => { obj.visible = !obj.visible; canvas.renderAll(); refreshLayers(); };

      const del = document.createElement('button');
      del.textContent = '🗑';
      del.onclick = () => { canvas.remove(obj); refreshLayers(); };

      const span = document.createElement('span');
      span.className = 'layer-btns';
      span.append(toggle, del);

      li.append(span);
      li.onclick = () => { canvas.setActiveObject(obj); canvas.renderAll(); };
      list.append(li);
    });
  }

  // ─── Cuadrícula ────────────────────────────────────────────────────────────
  function drawGrid() {
    const size = 50, w = canvas.getWidth(), h = canvas.getHeight();
    for (let i = 0; i < w/size; i++) {
      const line = new fabric.Line([i*size,0,i*size,h], {
        stroke: '#005555', selectable: false, evented: false, excludeFromExport: true
      });
      canvas.add(line).sendToBack();
    }
    for (let i = 0; i < h/size; i++) {
      const line = new fabric.Line([0,i*size,w,i*size], {
        stroke: '#005555', selectable: false, evented: false, excludeFromExport: true
      });
      canvas.add(line).sendToBack();
    }
  }
  drawGrid();

  // ─── Zoom (throttle) ───────────────────────────────────────────────────────
  let zoomT;
  canvas.on('mouse:wheel', opt => {
    clearTimeout(zoomT);
    zoomT = setTimeout(() => {
      let z = canvas.getZoom() * (0.999 ** opt.e.deltaY);
      z = Math.min(5, Math.max(0.2, z));
      canvas.zoomToPoint({ x: opt.e.offsetX, y: opt.e.offsetY }, z);
      canvas.requestRenderAll();
    }, 50);
    opt.e.preventDefault();
    opt.e.stopPropagation();
  });
});
