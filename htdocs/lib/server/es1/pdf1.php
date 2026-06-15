 
 <!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Convertir Imágenes a PDF (Modal + Neón + Rotar/Recortar)</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.css" rel="stylesheet">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.0/Sortable.min.js"></script>
  <style>
    body {
      background: #0d0d0d;
      color: #fff;
      margin: 0;
    }
    h1 {
      color: #00ffff;
      font-size: 2rem;
      text-align: center;
    }
    .btn-neon {
      background-color: #111;
      border: 2px solid #00ffff;
      color: #00ffff;
      box-shadow: 0 0 5px #00ffff, inset 0 0 10px #00ffff;
      transition: 0.3s;
      border-radius: 0 !important;
    }
    .btn-neon:hover {
      background-color: #00ffff;
      color: #0d0d0d;
      box-shadow: 0 0 10px #00ffff, inset 0 0 40px #00ffff;
    }
    .preview {
      border: 2px dashed #00ffff;
      background-color: rgba(0,255,255,0.05);
      display: flex; flex-wrap: wrap; gap: 10px; justify-content: center;
      padding: 10px; margin-top: 20px;
      position: relative;
    }
    .preview.dragover {
      background-color: rgba(0,255,255,0.1);
    }
    .preview img {
      max-width: 500px;
      height: auto;
      border: 2px solid #00ffff;
      box-shadow: 0 0 10px #00ffff;
      cursor: pointer;
      border-radius: 0 !important;
    }
    .preview .remove-btn {
      position: absolute;
      top: 5px;
      right: 5px;
      background: rgba(0,0,0,0.7);
      color: #fff;
      border: none;
      border-radius: 50%;
      font-size: 14px;
      cursor: pointer;
      z-index: 2;
    }
    .modal-header {
      padding: 1.5rem;
      border-bottom: 2px solid #00ffff;
      box-shadow: 0 2px 10px #00ffff;
      border-radius: 0 !important;
    }
    .modal-title {
      font-size: 1.5rem;
      color: #00ffff;
      text-shadow: 0 0 5px #00ffff, 0 0 10px #00ffff;
      letter-spacing: 1px;
    }
    .modal-content, .modal-body, .modal-footer, .btn {
      border-radius: 0 !important;
    }
    .crop-container {
      width: 100%;
      height: 70vh;
      margin: 0 auto;
      display: flex;
      justify-content: center;
      align-items: center;
      overflow: auto;
      background-color: #000;
    }
    #crop-image {
      max-width: none;
      max-height: none;
      border: 2px solid #00ffff;
      display: block;
    }
    @media (max-width: 576px) {
      h1 { font-size: 1.5rem; }
      .btn-neon { font-size: 0.9rem; padding: 0.5rem 1rem; }
      .modal-dialog { margin: 0; }
    }
    #preview-container {
        touch-action: none;
}
.preview-item button {
  pointer-events: auto;
  z-index: 10;
}
 

  </style>
</head>
<body>
  <div class="container py-5 text-center">
    <h1>Conversor de Imágenes a PDF</h1>
    <button class="btn btn-neon mt-4" data-bs-toggle="modal" data-bs-target="#imageModal">Abrir Herramienta</button>
    <div id="preview-container" class="preview">
      <p>No se ha seleccionado ninguna imagen</p>
    </div>
  </div>

  <!-- Modal principal -->
  <div class="modal fade" id="imageModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-xl" style="width: 90vw; max-width: 100%;">
      <div class="modal-content bg-dark text-white border border-info">
        <div class="modal-header">
          <h5 class="modal-title">Selecciona Imágenes</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <form id="image-form">
            <div class="mb-3">
              <label for="imageInput" class="form-label">Sube una o varias imágenes:</label>
              <input class="form-control" type="file" id="imageInput" accept="image/*" multiple>
            </div>
            <button type="submit" class="btn btn-neon w-100">Convertir a PDF</button>
            <div class="progress mt-3 d-none" id="progress-container">
              <div class="progress-bar progress-bar-striped progress-bar-animated" id="progress-bar" role="progressbar" style="width: 0%;">0%</div>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>

  <!-- Modal de recorte/rotación -->
  <div class="modal fade" id="cropModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-xl" style="width: 90vw; max-width: 100%;">
      <div class="modal-content bg-dark text-white border border-info">
        <div class="modal-header">
          <h5 class="modal-title">Recortar / Rotar Imagen</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body text-center">
          <div class="crop-container">
            <img id="crop-image" src="" alt="Imagen a recortar">
          </div>
          <div class="mt-3">
            <button id="rotate-left" class="btn btn-neon me-2">⟲</button>
            <button id="rotate-right" class="btn btn-neon">⟳</button>
          </div>
        </div>
        <div class="modal-footer border-0">
          <button id="zoom-in" class="btn btn-neon me-2">+</button>
          <button id="zoom-out" class="btn btn-neon me-2">–</button>
          <button type="button" class="btn btn-neon" id="crop-btn">Aplicar</button>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        </div>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
 <script>
  // Declaraciones iniciales
  const previewContainer = document.getElementById('preview-container');
  const imageInput = document.getElementById('imageInput');
  let imagesData = [];
  let cropper, currentIndex;

  // 1. Drag & Drop
  previewContainer.addEventListener('dragover', e => {
    e.preventDefault();
    previewContainer.classList.add('dragover');
  });
  previewContainer.addEventListener('dragleave', () => {
    previewContainer.classList.remove('dragover');
  });
  previewContainer.addEventListener('drop', e => {
    e.preventDefault();
    previewContainer.classList.remove('dragover');
    handleFiles(Array.from(e.dataTransfer.files));
  });

  // 2. Input change
  imageInput.addEventListener('change', () => {
    handleFiles(Array.from(imageInput.files));
  });

  function handleFiles(files) {
    if (previewContainer.querySelector('p')) previewContainer.innerHTML = '';
    files.forEach(file => {
      if (!file.type.startsWith('image/')) return;
      const reader = new FileReader();
      reader.onload = e => {
        const imgWrapper = document.createElement('div');
        imgWrapper.className = 'preview-item';
        imgWrapper.style.position = 'relative';

        // Botón ✎ Editar
        const editBtn = document.createElement('button');
        editBtn.className = 'edit-btn btn-neon';
        editBtn.textContent = '✎';
        editBtn.style.position = 'absolute';
        editBtn.style.left = '5px';
        editBtn.style.top = '5px';

        // Botón ❌ Eliminar
        const removeBtn = document.createElement('button');
        removeBtn.className = 'remove-btn btn-neon';
        removeBtn.textContent = '❌';
        removeBtn.style.position = 'absolute';
        removeBtn.style.right = '5px';
        removeBtn.style.top = '5px';

        // La imagen
        const img = document.createElement('img');
        img.src = e.target.result;
        img.style.touchAction = 'none'; // deja pasar el drag a Sortable

        // Asignamos índices
        imgWrapper.dataset.index = imagesData.length;
        img.dataset.index = imagesData.length;
        imgWrapper.dataset.src = e.target.result;

        // Eventos para botones (click + touch)
        ['click','pointerdown','touchstart'].forEach(evt => {
          editBtn.addEventListener(evt, ev => {
            ev.stopPropagation();
            showCropModal(imgWrapper.dataset.index);
          });
          removeBtn.addEventListener(evt, ev => {
            ev.stopPropagation();
            const idx = +imgWrapper.dataset.index;
            imagesData.splice(idx, 1);
            imgWrapper.remove();
            updateIndices();
          });
        });

        // Solo click para la imagen (no intercepta el drag)
        img.addEventListener('click', ev => {
          ev.stopPropagation();
          showCropModal(img.dataset.index);
        });

        // Ensamblamos
        imgWrapper.append(editBtn, removeBtn, img);
        previewContainer.appendChild(imgWrapper);
        imagesData.push(e.target.result);
      };
      reader.readAsDataURL(file);
    });
  }

  function updateIndices() {
    previewContainer.querySelectorAll('.preview-item').forEach((w, i) => {
      w.dataset.index = i;
      w.querySelector('img').dataset.index = i;
    });
  }

  // 3. Reordenar con SortableJS (drag en touch tras 200 ms)
  new Sortable(previewContainer, {
    animation: 150,
    delay: 200,
    delayOnTouchOnly: true,
    onEnd: () => {
      const newOrder = [];
      previewContainer.querySelectorAll('img').forEach((img, i) => {
        newOrder.push(imagesData[img.dataset.index]);
        img.dataset.index = i;
      });
      imagesData = newOrder;
    }
  });

  // 4. Generar PDF con barra de progreso
  document.getElementById('image-form').addEventListener('submit', e => {
    e.preventDefault();
    if (!imagesData.length) return alert('Selecciona al menos una imagen');
    const { jsPDF } = window.jspdf;
    const pdf = new jsPDF();
    const progressContainer = document.getElementById('progress-container');
    const progressBar = document.getElementById('progress-bar');
    progressContainer.classList.remove('d-none');

    let idx = 0;
    const addPage = () => {
      const img = new Image();
      img.src = imagesData[idx];
      img.onload = () => {
        const w = pdf.internal.pageSize.getWidth();
        const h = img.height * w / img.width;
        if (idx) pdf.addPage();
        pdf.addImage(img, 'PNG', 0, 0, w, h);
        idx++;
        const pct = Math.floor((idx / imagesData.length) * 100);
        progressBar.style.width = pct + '%';
        progressBar.textContent = pct + '%';
        if (idx < imagesData.length) addPage();
        else {
          pdf.save('imagenes.pdf');
          progressContainer.classList.add('d-none');
          bootstrap.Modal.getInstance(document.getElementById('imageModal')).hide();
        }
      };
    };
    addPage();
  });

  // Mostrar modal de recorte
  function showCropModal(index) {
    currentIndex = +index;
    const cropImg = document.getElementById('crop-image');
    cropImg.src = imagesData[currentIndex];
    const modal = new bootstrap.Modal(document.getElementById('cropModal'));
    modal.show();
    setTimeout(() => {
      if (cropper) cropper.destroy();
      cropper = new Cropper(cropImg, {
        aspectRatio: NaN,
        viewMode: 1,
        autoCrop: false,
        responsive: true,
        ready() {
          cropper.reset();
          cropper.zoomTo(1);
        }
      });
    }, 400);
  }

  // Rotar / Zoom / Aplicar recorte
  document.getElementById('rotate-left').addEventListener('click', () => cropper?.rotate(-90));
  document.getElementById('rotate-right').addEventListener('click', () => cropper?.rotate(90));
  document.getElementById('zoom-in').addEventListener('click', () => cropper?.zoom(0.1));
  document.getElementById('zoom-out').addEventListener('click', () => cropper?.zoom(-0.1));
  document.getElementById('crop-btn').addEventListener('click', () => {
    const canvas = cropper.getCroppedCanvas();
    const dataURL = canvas.toDataURL('image/png');
    imagesData[currentIndex] = dataURL;
    previewContainer.querySelectorAll('img')[currentIndex].src = dataURL;
    bootstrap.Modal.getInstance(document.getElementById('cropModal')).hide();
  });
</script>

</body>
</html>
