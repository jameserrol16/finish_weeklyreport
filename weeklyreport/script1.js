/* ============================================================
   SIGNATURE MODAL — Type / Draw / Upload
   ============================================================ */

function openSignatureModal(triggerBtn) {
    const existing = document.getElementById('sigModal');
    if (existing) existing.remove();

    const modal = document.createElement('div');
    modal.id = 'sigModal';
    modal.innerHTML = `
        <div class="sig-modal-overlay" id="sigModalOverlay"></div>
        <div class="sig-modal-box">
            <div class="sig-modal-header">
                <span>Add Signature</span>
                <button class="sig-modal-close" id="sigModalClose">&times;</button>
            </div>
            <div class="sig-modal-tabs">
                <button class="sig-tab active" data-tab="type">✍ Type</button>
                <button class="sig-tab" data-tab="draw">🖊 Draw</button>
                <button class="sig-tab" data-tab="upload">📁 Upload</button>
            </div>

            <!-- TYPE TAB -->
            <div class="sig-tab-content active" id="tab-type">
                <p class="sig-tab-hint">Type your name and choose a style</p>
                <input type="text" id="sigTypeInput" placeholder="Your full name" maxlength="60">
                <div class="sig-font-options" id="sigFontOptions">
                    <div class="sig-font-opt selected" data-font="'Dancing Script', cursive" style="font-family:'Dancing Script',cursive;">John Smith</div>
                    <div class="sig-font-opt" data-font="'Pacifico', cursive" style="font-family:'Pacifico',cursive;">John Smith</div>
                    <div class="sig-font-opt" data-font="'Great Vibes', cursive" style="font-family:'Great Vibes',cursive;">John Smith</div>
                    <div class="sig-font-opt" data-font="'Caveat', cursive" style="font-family:'Caveat',cursive;">John Smith</div>
                    <div class="sig-font-opt" data-font="'Sacramento', cursive" style="font-family:'Sacramento',cursive;">John Smith</div>
                </div>
                <canvas id="sigTypeCanvas" width="400" height="100"></canvas>
                <div class="sig-color-row">
                    <label>Color:</label>
                    <input type="color" id="sigTypeColor" value="#000000">
                </div>
            </div>

            <!-- DRAW TAB -->
            <div class="sig-tab-content" id="tab-draw">
                <p class="sig-tab-hint">Draw your signature below</p>
                <div class="sig-canvas-wrap">
                    <canvas id="sigDrawCanvas" width="400" height="130"></canvas>
                </div>
                <div class="sig-color-row">
                    <label>Color:</label>
                    <input type="color" id="sigDrawColor" value="#000000">
                    <button class="sig-clear-btn" id="sigDrawClear">Clear</button>
                </div>
            </div>

            <!-- UPLOAD TAB -->
            <div class="sig-tab-content" id="tab-upload">
                <p class="sig-tab-hint">Upload an image of your signature</p>
                <div class="sig-upload-drop" id="sigUploadDrop">
                    <span>📂</span><br>
                    <span>Click or drag & drop an image</span>
                    <input type="file" accept="image/*" id="sigUploadFile">
                </div>
                <canvas id="sigUploadCanvas" width="400" height="130" style="display:none;"></canvas>
            </div>

            <div class="sig-modal-footer">
                <button class="sig-cancel-btn" id="sigCancelBtn">Cancel</button>
                <button class="sig-apply-btn" id="sigApplyBtn">Apply Signature</button>
            </div>
        </div>
    `;
    document.body.appendChild(modal);

    if (!document.getElementById('sigGFonts')) {
        const link = document.createElement('link');
        link.id = 'sigGFonts';
        link.rel = 'stylesheet';
        link.href = 'https://fonts.googleapis.com/css2?family=Dancing+Script:wght@700&family=Pacifico&family=Great+Vibes&family=Caveat:wght@700&family=Sacramento&display=swap';
        document.head.appendChild(link);
    }

    let activeTab = 'type';
    let selectedFont = "'Dancing Script', cursive";
    let uploadedImageData = null;

    modal.querySelectorAll('.sig-tab').forEach(tab => {
        tab.addEventListener('click', () => {
            modal.querySelectorAll('.sig-tab').forEach(t => t.classList.remove('active'));
            modal.querySelectorAll('.sig-tab-content').forEach(c => c.classList.remove('active'));
            tab.classList.add('active');
            activeTab = tab.dataset.tab;
            modal.querySelector(`#tab-${activeTab}`).classList.add('active');
            if (activeTab === 'type') renderTypePreview();
            if (activeTab === 'draw') initDrawCanvas();
        });
    });

    const typeInput  = modal.querySelector('#sigTypeInput');
    const typeColor  = modal.querySelector('#sigTypeColor');
    const typeCanvas = modal.querySelector('#sigTypeCanvas');
    const fontOpts   = modal.querySelectorAll('.sig-font-opt');

    fontOpts.forEach(opt => {
        opt.addEventListener('click', () => {
            fontOpts.forEach(o => o.classList.remove('selected'));
            opt.classList.add('selected');
            selectedFont = opt.dataset.font;
            renderTypePreview();
        });
    });

    function renderTypePreview() {
        const ctx = typeCanvas.getContext('2d');
        ctx.clearRect(0, 0, typeCanvas.width, typeCanvas.height);
        const name = typeInput.value.trim() || 'Your Name';
        ctx.fillStyle = typeColor.value;
        ctx.font = `48px ${selectedFont}`;
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        fontOpts.forEach(opt => { opt.textContent = name; });
        ctx.fillText(name, typeCanvas.width / 2, typeCanvas.height / 2);
    }

    typeInput.addEventListener('input', renderTypePreview);
    typeColor.addEventListener('input', renderTypePreview);
    setTimeout(renderTypePreview, 600);

    const drawCanvas = modal.querySelector('#sigDrawCanvas');
    const drawColor  = modal.querySelector('#sigDrawColor');
    const drawClear  = modal.querySelector('#sigDrawClear');
    let drawCtx, drawing = false, lastX, lastY;

    function initDrawCanvas() {
        drawCtx = drawCanvas.getContext('2d');
        drawCtx.lineCap = 'round';
        drawCtx.lineJoin = 'round';
        drawCtx.lineWidth = 2.5;
        drawCtx.strokeStyle = drawColor.value;
    }

    function getPos(e, canvas) {
        const rect = canvas.getBoundingClientRect();
        const scaleX = canvas.width / rect.width;
        const scaleY = canvas.height / rect.height;
        if (e.touches) {
            return {
                x: (e.touches[0].clientX - rect.left) * scaleX,
                y: (e.touches[0].clientY - rect.top) * scaleY
            };
        }
        return {
            x: (e.clientX - rect.left) * scaleX,
            y: (e.clientY - rect.top) * scaleY
        };
    }

    drawCanvas.addEventListener('mousedown', e => {
        drawing = true;
        const p = getPos(e, drawCanvas);
        lastX = p.x; lastY = p.y;
    });
    drawCanvas.addEventListener('mousemove', e => {
        if (!drawing || !drawCtx) return;
        const p = getPos(e, drawCanvas);
        drawCtx.strokeStyle = drawColor.value;
        drawCtx.beginPath();
        drawCtx.moveTo(lastX, lastY);
        drawCtx.lineTo(p.x, p.y);
        drawCtx.stroke();
        lastX = p.x; lastY = p.y;
    });
    drawCanvas.addEventListener('mouseup', () => drawing = false);
    drawCanvas.addEventListener('mouseleave', () => drawing = false);

    drawCanvas.addEventListener('touchstart', e => {
        e.preventDefault();
        drawing = true;
        const p = getPos(e, drawCanvas);
        lastX = p.x; lastY = p.y;
    }, { passive: false });
    drawCanvas.addEventListener('touchmove', e => {
        e.preventDefault();
        if (!drawing || !drawCtx) return;
        const p = getPos(e, drawCanvas);
        drawCtx.strokeStyle = drawColor.value;
        drawCtx.beginPath();
        drawCtx.moveTo(lastX, lastY);
        drawCtx.lineTo(p.x, p.y);
        drawCtx.stroke();
        lastX = p.x; lastY = p.y;
    }, { passive: false });
    drawCanvas.addEventListener('touchend', () => drawing = false);

    drawColor.addEventListener('input', () => {
        if (drawCtx) drawCtx.strokeStyle = drawColor.value;
    });
    drawClear.addEventListener('click', () => {
        if (drawCtx) drawCtx.clearRect(0, 0, drawCanvas.width, drawCanvas.height);
    });

    const uploadDrop   = modal.querySelector('#sigUploadDrop');
    const uploadFile   = modal.querySelector('#sigUploadFile');
    const uploadCanvas = modal.querySelector('#sigUploadCanvas');

    uploadDrop.addEventListener('click', () => uploadFile.click());
    uploadDrop.addEventListener('dragover', e => { e.preventDefault(); uploadDrop.classList.add('drag-over'); });
    uploadDrop.addEventListener('dragleave', () => uploadDrop.classList.remove('drag-over'));
    uploadDrop.addEventListener('drop', e => {
        e.preventDefault();
        uploadDrop.classList.remove('drag-over');
        const file = e.dataTransfer.files[0];
        if (file && file.type.startsWith('image/')) handleUploadFile(file);
    });
    uploadFile.addEventListener('change', () => {
        if (uploadFile.files[0]) handleUploadFile(uploadFile.files[0]);
    });

    function handleUploadFile(file) {
        const reader = new FileReader();
        reader.onload = e => {
            uploadedImageData = e.target.result;
            const img = new Image();
            img.onload = () => {
                uploadDrop.style.display = 'none';
                uploadCanvas.style.display = 'block';
                const ctx = uploadCanvas.getContext('2d');
                ctx.clearRect(0, 0, uploadCanvas.width, uploadCanvas.height);
                const scale = Math.min(uploadCanvas.width / img.width, uploadCanvas.height / img.height);
                const w = img.width * scale;
                const h = img.height * scale;
                const x = (uploadCanvas.width - w) / 2;
                const y = (uploadCanvas.height - h) / 2;
                ctx.drawImage(img, x, y, w, h);
            };
            img.src = uploadedImageData;
        };
        reader.readAsDataURL(file);
    }

    modal.querySelector('#sigApplyBtn').addEventListener('click', () => {
        let finalDataURL = null;

        if (activeTab === 'type') {
            renderTypePreview();
            finalDataURL = trimCanvas(typeCanvas);
        } else if (activeTab === 'draw') {
            if (!drawCtx || isCanvasBlank(drawCanvas)) {
                alert('Please draw your signature first.');
                return;
            }
            finalDataURL = trimCanvas(drawCanvas);
        } else if (activeTab === 'upload') {
            if (!uploadedImageData) {
                alert('Please upload an image first.');
                return;
            }
            finalDataURL = trimCanvas(uploadCanvas);
        }

        if (finalDataURL) {
            applySignatureToContainer(triggerBtn, finalDataURL);
            modal.remove();
        }
    });

    const closeModal = () => modal.remove();
    modal.querySelector('#sigModalClose').addEventListener('click', closeModal);
    modal.querySelector('#sigCancelBtn').addEventListener('click', closeModal);
    modal.querySelector('#sigModalOverlay').addEventListener('click', closeModal);
}

function isCanvasBlank(canvas) {
    const ctx = canvas.getContext('2d');
    const px = ctx.getImageData(0, 0, canvas.width, canvas.height).data;
    for (let i = 3; i < px.length; i += 4) {
        if (px[i] > 0) return false;
    }
    return true;
}

function trimCanvas(canvas) {
    const ctx = canvas.getContext('2d');
    const { width, height } = canvas;
    const imageData = ctx.getImageData(0, 0, width, height);
    const { data } = imageData;
    let top = height, bottom = 0, left = width, right = 0;

    for (let y = 0; y < height; y++) {
        for (let x = 0; x < width; x++) {
            const alpha = data[(y * width + x) * 4 + 3];
            if (alpha > 0) {
                if (y < top) top = y;
                if (y > bottom) bottom = y;
                if (x < left) left = x;
                if (x > right) right = x;
            }
        }
    }

    if (top > bottom || left > right) return canvas.toDataURL();

    const pad = 10;
    top    = Math.max(0, top - pad);
    bottom = Math.min(height - 1, bottom + pad);
    left   = Math.max(0, left - pad);
    right  = Math.min(width - 1, right + pad);

    const trimmed = document.createElement('canvas');
    trimmed.width  = right - left + 1;
    trimmed.height = bottom - top + 1;
    trimmed.getContext('2d').putImageData(
        ctx.getImageData(left, top, trimmed.width, trimmed.height),
        0, 0
    );
    return trimmed.toDataURL();
}

function applySignatureToContainer(triggerBtn, dataURL) {
    const container = triggerBtn.closest('.sig-container');
    const sigBox    = container.querySelector('.sig-box');
    const img       = sigBox.querySelector('img');
    const deleteBtn = sigBox.querySelector('.sig-delete');

    img.src = dataURL;
    sigBox.style.display = 'block';
    sigBox.style.width   = '140px';
    sigBox.style.height  = '70px';
    sigBox.style.left    = ((container.clientWidth - 140) / 2) + 'px';
    sigBox.style.top     = '5px';
    enableDrag(sigBox);
    deleteBtn.style.display = 'block';
    triggerBtn.style.display = 'none';

    deleteBtn.onclick = () => {
        if (!confirm('Remove this signature?')) return;
        img.src = '';
        sigBox.style.display = 'none';
        sigBox.style.transform = 'translate(0,0)';
        sigBox.style.left = '';
        sigBox.style.top  = '';
        triggerBtn.style.display = 'flex';
        deleteBtn.style.display  = 'none';
    };
}

/* ============================================================
   DATE RANGE HELPERS (date pickers)
   ============================================================ */
function updateWeekRange() {
    const startEl  = document.getElementById('rangeStart');
    const endEl    = document.getElementById('rangeEnd');
    const hidden   = document.getElementById('weekRangeHidden');
    const errorEl  = document.getElementById('dateRangeError');
    if (!startEl || !endEl) return;

    // Validate: From must not be later than To
    if (startEl.value && endEl.value && startEl.value > endEl.value) {
        if (errorEl) errorEl.style.display = 'block';
        if (hidden)  hidden.value = '';
        return;
    } else {
        if (errorEl) errorEl.style.display = 'none';
    }

    // Build formatted string e.g. "March 17, 2026 - March 21, 2026"
    const monthNames = ['January','February','March','April','May','June',
                        'July','August','September','October','November','December'];
    let rangeStr = '';
    if (startEl.value) {
        const s = new Date(startEl.value + 'T00:00:00');
        rangeStr += `${monthNames[s.getMonth()]} ${s.getDate()}, ${s.getFullYear()}`;
    }
    if (endEl.value) {
        const e = new Date(endEl.value + 'T00:00:00');
        rangeStr += (rangeStr ? ' to ' : '') + `${monthNames[e.getMonth()]} ${e.getDate()}, ${e.getFullYear()}`;
    }
    if (hidden) hidden.value = rangeStr;
}

function enforceRowDateLimits() {
    const start = document.getElementById('rangeStart')?.value;
    const end   = document.getElementById('rangeEnd')?.value;
    document.querySelectorAll('#accomplishmentTable tbody tr').forEach(row => {
        const dateInput = row.querySelector('input[type="date"]');
        if (dateInput) {
            if (start) dateInput.min = start;
            if (end)   dateInput.max = end;
            if (dateInput.value && start && dateInput.value < start) dateInput.value = '';
            if (dateInput.value && end   && dateInput.value > end)   dateInput.value = '';
        }
    });
}

/* ============================================================
   ROWS
   ============================================================ */
function addRow() {
    const tbody    = document.querySelector("#accomplishmentTable tbody");
    const colCount = document.querySelectorAll("#headerRow th").length;
    const tr       = document.createElement("tr");

    for (let i = 0; i < colCount; i++) {
        const td = document.createElement("td");
        if (i === 0) {
            td.innerHTML = `
                <input type="date" style="width:100%;">
                <input type="text" class="work-mode" placeholder="On-site / WFH" style="text-align:center; width:100%;">`;
        } else if (i === 2) {
            td.innerHTML = `
                <textarea list="descOptions" placeholder="Type description or select..."></textarea>
                <datalist id="descOptions">
                    <option value="Holiday">
                    <option value="Suspended">
                </datalist>`;
        } else {
            td.innerHTML = "<textarea></textarea>";
        }
        tr.appendChild(td);
    }

    tbody.appendChild(tr);
    tr.querySelectorAll("textarea").forEach(t => {
        autoExpand(t);
        t.addEventListener("input", () => autoExpand(t));
    });

    // Apply date limits to newly added row
    enforceRowDateLimits();
}

function removeLastRow() {
    const tbody = document.querySelector("#accomplishmentTable tbody");
    if (tbody.rows.length > 0) tbody.deleteRow(-1);
}

/* ============================================================
   COLUMN ADD / REMOVE
   ============================================================ */
function addColumn(btn) {
    const index = btn.closest("th").cellIndex;
    const name  = prompt("Column name:");
    if (!name) return;

    const th = document.createElement("th");
    th.innerHTML = `
        ${name}
        <div class="col-controls">
            <div class="control-btn" onclick="addColumn(this)">+</div>
            <div class="control-btn" onclick="removeColumn(this)">−</div>
        </div>`;
    headerRow.insertBefore(th, headerRow.children[index + 1]);

    document.querySelectorAll("#accomplishmentTable tbody tr").forEach(row => {
        const td       = document.createElement("td");
        const textarea = document.createElement("textarea");
        textarea.placeholder = "Type here...";
        textarea.addEventListener("input", () => autoExpand(textarea));
        autoExpand(textarea);
        td.appendChild(textarea);
        row.insertBefore(td, row.children[index + 1]);
    });
}

function removeColumn(btn) {
    const index = btn.closest("th").cellIndex;
    if (headerRow.children.length <= 1) return;
    headerRow.deleteCell(index);
    document.querySelectorAll("#accomplishmentTable tbody tr").forEach(row => row.deleteCell(index));
}

/* ============================================================
   DRAG & RESIZE SIGNATURE BOX
   ============================================================ */
function enableDrag(el) {
    let startX = 0, startY = 0, x = 0, y = 0;
    let startW, startH;
    let dragging = false, resizing = false;
    const resizer = el.querySelector(".sig-resizer");

    el.addEventListener("mousedown", e => {
        if (e.target === resizer) return;
        dragging = true;
        startX = e.clientX - x;
        startY = e.clientY - y;
        e.preventDefault();
    });

    document.addEventListener("mousemove", e => {
        if (dragging) {
            x = e.clientX - startX;
            y = e.clientY - startY;
            el.style.transform = `translate(${x}px, ${y}px)`;
        }
        if (resizing) {
            el.style.width  = (startW + e.clientX - startX) + "px";
            el.style.height = (startH + e.clientY - startY) + "px";
        }
    });

    document.addEventListener("mouseup", () => {
        if (dragging) {
            const parent     = el.parentElement;
            const rect       = el.getBoundingClientRect();
            const parentRect = parent.getBoundingClientRect();
            const leftPx     = rect.left - parentRect.left;
            const topPx      = rect.top  - parentRect.top;
            el.style.left      = leftPx + "px";
            el.style.top       = topPx  + "px";
            el.dataset.leftPct = (leftPx / parentRect.width  * 100).toFixed(4);
            el.dataset.topPct  = (topPx  / parentRect.height * 100).toFixed(4);
            el.style.transform = "none";
            x = 0; y = 0;
        }
        dragging  = false;
        resizing  = false;
    });

    resizer.addEventListener("mousedown", e => {
        resizing = true;
        startX   = e.clientX;
        startY   = e.clientY;
        startW   = el.offsetWidth;
        startH   = el.offsetHeight;
        e.stopPropagation();
        e.preventDefault();
    });
}

/* ============================================================
   EXPORT PDF
   ============================================================ */
async function exportPDF() {
    try {
        const { jsPDF } = window.jspdf;
        const wasPreview = isPreview;
        if (!isPreview) togglePreview();

        const uiSelectors = ".table-actions, .top-right, .col-controls, .sig-resizer, .sig-delete, .sig-icon-btn";
        const hiddenEls = [];
        reportPage.querySelectorAll(uiSelectors).forEach(el => {
            if (el.style.display !== "none") {
                el.style.display = "none";
                hiddenEls.push(el);
            }
        });

        const origBoxShadow    = reportPage.style.boxShadow;
        const origBorderRadius = reportPage.style.borderRadius;
        const origBorder       = reportPage.style.border;
        reportPage.style.boxShadow    = "none";
        reportPage.style.borderRadius = "0";
        reportPage.style.border       = "none";

        await new Promise(r => setTimeout(r, 120));

        const canvas = await html2canvas(reportPage, {
            scale: 2,
            useCORS: true,
            backgroundColor: "#ffffff",
            scrollX: 0,
            scrollY: -window.scrollY,
            onclone: (clonedDoc) => {

                // ── FIX: Hide date pickers, show formatted range text ──
                const clonedPickerRow    = clonedDoc.getElementById('datePickerRow');
                const clonedRangeDisplay = clonedDoc.getElementById('weekRangeDisplay');
                const clonedStartEl      = clonedDoc.getElementById('rangeStart');
                const clonedEndEl        = clonedDoc.getElementById('rangeEnd');
                const monthNames = ['January','February','March','April','May','June',
                                    'July','August','September','October','November','December'];
                let rangeStr = '';
                if (clonedStartEl && clonedStartEl.value) {
                    const s = new Date(clonedStartEl.value + 'T00:00:00');
                    rangeStr += `${monthNames[s.getMonth()]} ${s.getDate()}, ${s.getFullYear()}`;
                }
                if (clonedEndEl && clonedEndEl.value) {
                    const e = new Date(clonedEndEl.value + 'T00:00:00');
                    rangeStr += ` to ${monthNames[e.getMonth()]} ${e.getDate()}, ${e.getFullYear()}`;
                }
                if (clonedRangeDisplay) clonedRangeDisplay.textContent = rangeStr;
                if (clonedPickerRow)    clonedPickerRow.style.display    = 'none';
                if (clonedRangeDisplay) clonedRangeDisplay.style.display = 'block';

                clonedDoc.querySelectorAll('.signature-table td.signature').forEach(td => {
                    td.style.overflow      = 'visible';
                    td.style.height        = 'auto';
                    td.style.paddingBottom = '20px';
                });
                clonedDoc.querySelectorAll('.sig-container').forEach(c => {
                    c.style.overflow     = 'visible';
                    c.style.marginBottom = '8px';
                });
                clonedDoc.querySelectorAll('.sig-text').forEach(el => {
                    el.style.display    = 'block';
                    el.style.visibility = 'visible';
                    el.style.height     = 'auto';
                    el.style.overflow   = 'visible';
                });
                clonedDoc.querySelectorAll('input:not([type="file"]):not([type="hidden"]), textarea').forEach(el => {
                    // Skip the date pickers — already hidden above
                    if (el.id === 'rangeStart' || el.id === 'rangeEnd') return;

                    const div = clonedDoc.createElement('div');

                    // Format date inputs as "Month Day, Year" text
                    if (el.type === 'date' && el.value) {
                        const d = new Date(el.value + 'T00:00:00');
                        const monthNames = ['January','February','March','April','May','June',
                                            'July','August','September','October','November','December'];
                        div.textContent = `${monthNames[d.getMonth()]} ${d.getDate()}, ${d.getFullYear()}`;
                    } else {
                        div.textContent = el.value || '';
                    }
                    const computed  = window.getComputedStyle(el);
                    div.style.cssText    = el.style.cssText;
                    div.style.fontFamily = computed.fontFamily;
                    div.style.fontSize   = computed.fontSize;
                    div.style.fontWeight = computed.fontWeight;
                    div.style.color      = computed.color || '#000';
                    div.style.lineHeight = computed.lineHeight;
                    div.style.padding    = computed.padding;
                    div.style.margin     = '0';
                    div.style.border     = 'none';
                    div.style.background = 'transparent';
                    div.style.width      = '100%';
                    div.style.boxSizing  = 'border-box';
                    div.style.whiteSpace = 'pre-wrap';
                    div.style.wordBreak  = 'break-word';
                    div.style.overflow   = 'visible';
                    div.style.minHeight  = computed.height;
                    div.style.textAlign  = computed.textAlign;
                    el.parentNode.replaceChild(div, el);
                });
            }
        });

        hiddenEls.forEach(el => el.style.display = "");
        reportPage.style.boxShadow    = origBoxShadow;
        reportPage.style.borderRadius = origBorderRadius;
        reportPage.style.border       = origBorder;

        if (!wasPreview) togglePreview();

        const imgData    = canvas.toDataURL("image/png");
        const pdf        = new jsPDF("p", "mm", "a4");
        const pageW      = 210;
        const pageH      = 297;
        const imgW       = pageW;
        const imgH       = canvas.height * pageW / canvas.width;
        const totalPages = Math.ceil(imgH / pageH);

        for (let i = 0; i < totalPages; i++) {
            if (i > 0) pdf.addPage();
            const srcY        = i * pageH * (canvas.width / pageW);
            const srcH        = pageH * (canvas.width / pageW);
            const sliceCanvas = document.createElement("canvas");
            sliceCanvas.width  = canvas.width;
            sliceCanvas.height = Math.min(srcH, canvas.height - srcY);
            const ctx = sliceCanvas.getContext("2d");
            ctx.drawImage(canvas, 0, srcY, canvas.width, sliceCanvas.height, 0, 0, canvas.width, sliceCanvas.height);
            const sliceData = sliceCanvas.toDataURL("image/png");
            const sliceH    = sliceCanvas.height * pageW / canvas.width;
            pdf.addImage(sliceData, "PNG", 0, 0, imgW, sliceH);
        }

        pdf.save("Weekly_Accomplishment_Report.pdf");
    } catch (err) {
        alert("PDF export failed: " + err.message);
        console.error("exportPDF error:", err);
    }
}

/* ============================================================
   PREVIEW TOGGLE
   ============================================================ */
let isPreview = false;

function togglePreview() {
    isPreview = !isPreview;
    reportPage.classList.toggle("preview-mode");

    const datePickerRow    = document.getElementById('datePickerRow');
    const weekRangeDisplay = document.getElementById('weekRangeDisplay');

    if (isPreview) {
        previewBtn.textContent = "✏ Exit Preview";

        // Format date range pickers as text and hide picker row
        if (datePickerRow) {
            const startEl = document.getElementById('rangeStart');
            const endEl   = document.getElementById('rangeEnd');
            const monthNames = ['January','February','March','April','May','June',
                                'July','August','September','October','November','December'];
            let rangeStr = '';
            if (startEl && startEl.value) {
                const s = new Date(startEl.value + 'T00:00:00');
                rangeStr += `${monthNames[s.getMonth()]} ${s.getDate()}, ${s.getFullYear()}`;
            }
            if (endEl && endEl.value) {
                const e = new Date(endEl.value + 'T00:00:00');
                rangeStr += ` to ${monthNames[e.getMonth()]} ${e.getDate()}, ${e.getFullYear()}`;
            }
            if (weekRangeDisplay) weekRangeDisplay.textContent = rangeStr;
            datePickerRow.style.display = 'none';
        }
        if (weekRangeDisplay) weekRangeDisplay.style.display = 'block';

        reportPage.querySelectorAll(".sig-text").forEach(el => {
            el.setAttribute("data-has-value", el.value.trim() ? "true" : "false");
        });
        reportPage.querySelectorAll(".sig-delete").forEach(el => el.style.display = "none");
        reportPage.querySelectorAll(".sig-icon-btn").forEach(el => el.style.display = "none");
        reportPage.querySelectorAll("input, textarea, select").forEach(el => {
            if (el.type === "file" || el.closest(".signature")) return;
            if (el.id === 'rangeStart' || el.id === 'rangeEnd' || el.id === 'weekRangeHidden') return;

            // For date inputs, replace with a span showing formatted text
            if (el.type === 'date') {
                if (el.value) {
                    const d = new Date(el.value + 'T00:00:00');
                    const monthNames = ['January','February','March','April','May','June',
                                        'July','August','September','October','November','December'];
                    const formatted = `${monthNames[d.getMonth()]} ${d.getDate()}, ${d.getFullYear()}`;
                    const span = document.createElement('span');
                    span.className = 'date-preview-text';
                    span.textContent = formatted;
                    span.style.cssText = 'display:block; text-align:center; font-size:12px; font-family:inherit; padding:4px 6px;';
                    el.dataset.previewReplaced = 'true';
                    el.style.display = 'none';
                    el.parentNode.insertBefore(span, el.nextSibling);
                } else {
                    el.style.display = 'none';
                }
                return;
            }

            if (!el.value || !el.value.trim()) el.style.display = "none";
        });

    } else {
        previewBtn.textContent = "👁 Preview";

        // Show pickers, hide formatted string
        if (datePickerRow)    datePickerRow.style.display    = 'flex';
        if (weekRangeDisplay) weekRangeDisplay.style.display = 'none';

        reportPage.querySelectorAll(".sig-box").forEach(box => {
            const img       = box.querySelector("img");
            const deleteBtn = box.querySelector(".sig-delete");
            if (img && img.src && img.src.startsWith("data:image")) {
                deleteBtn.style.display = "block";
            } else {
                deleteBtn.style.display = "none";
            }
        });
        reportPage.querySelectorAll('.sig-container').forEach(container => {
            const iconBtn = container.querySelector('.sig-icon-btn');
            const sigBox  = container.querySelector('.sig-box');
            const sigImg  = sigBox ? sigBox.querySelector('img') : null;
            const hasSig  = sigImg && sigImg.src && sigImg.src.startsWith("data:image");
            if (iconBtn && !iconBtn.disabled) iconBtn.style.display = hasSig ? "none" : "flex";
            if (sigBox && hasSig) sigBox.style.display = "block";
        });
        reportPage.querySelectorAll(".sig-text").forEach(el => el.style.display = "block");
        reportPage.querySelectorAll("input, textarea, select").forEach(el => {
            el.style.display = "";
        });
        // Remove date preview spans and restore date inputs
        reportPage.querySelectorAll('.date-preview-text').forEach(span => span.remove());
        reportPage.querySelectorAll('input[type="date"]').forEach(el => {
            el.style.display = '';
            delete el.dataset.previewReplaced;
        });
        // Re-enforce: pickers visible, display text hidden
        if (datePickerRow)    datePickerRow.style.display    = 'flex';
        if (weekRangeDisplay) weekRangeDisplay.style.display = 'none';
    }
}

/* ============================================================
   AUTO EXPAND TEXTAREA
   ============================================================ */
function autoExpand(el) {
    el.style.height = "auto";
    el.style.height = el.scrollHeight + "px";
}

/* ============================================================
   DOM READY
   ============================================================ */
document.addEventListener("DOMContentLoaded", () => {
    // Wire up date range pickers
    const startEl = document.getElementById('rangeStart');
    const endEl   = document.getElementById('rangeEnd');
    if (startEl) startEl.addEventListener('change', function() {
        updateWeekRange();
        enforceRowDateLimits();
    });
    if (endEl) endEl.addEventListener('change', function() {
        updateWeekRange();
        enforceRowDateLimits();
    });
    // Init display for existing saved reports
    updateWeekRange();

    // Load saved accomplishments if editing, otherwise add one blank row
    if (window.savedAccomplishments && window.savedAccomplishments.length > 0) {
        window.savedAccomplishments.forEach(row => {
            const tbody    = document.querySelector("#accomplishmentTable tbody");
            const colCount = document.querySelectorAll("#headerRow th").length;
            const tr       = document.createElement("tr");

            for (let i = 0; i < colCount; i++) {
                const td = document.createElement("td");
                if (i === 0) {
                    const dateInput = document.createElement("input");
                    dateInput.type  = "date";
                    dateInput.style.width = "100%";
                    dateInput.value = row.day || '';

                    const modeInput = document.createElement("input");
                    modeInput.type  = "text";
                    modeInput.className = "work-mode";
                    modeInput.placeholder = "On-site / WFH";
                    modeInput.style.textAlign = "center";
                    modeInput.style.width = "100%";
                    modeInput.value = row.mode || '';

                    td.appendChild(dateInput);
                    td.appendChild(modeInput);
                } else if (i === 1) {
                    const ta = document.createElement("textarea");
                    ta.value = row.accomplishment || '';
                    autoExpand(ta);
                    ta.addEventListener("input", () => autoExpand(ta));
                    td.appendChild(ta);
                } else if (i === 2) {
                    const ta = document.createElement("textarea");
                    ta.value = row.description || '';
                    autoExpand(ta);
                    ta.addEventListener("input", () => autoExpand(ta));
                    const dl = document.createElement("datalist");
                    dl.id = "descOptions";
                    dl.innerHTML = '<option value="Holiday"><option value="Suspended">';
                    ta.setAttribute("list", "descOptions");
                    td.appendChild(ta);
                    td.appendChild(dl);
                } else {
                    const ta = document.createElement("textarea");
                    ta.value = '';
                    autoExpand(ta);
                    ta.addEventListener("input", () => autoExpand(ta));
                    td.appendChild(ta);
                }
                tr.appendChild(td);
            }
            tbody.appendChild(tr);
        });
        enforceRowDateLimits();
    } else {
        addRow();
    }

    // Restore sig-text values (Verified/Approved by name & position)
    if (window.savedSigTexts && window.savedSigTexts.length > 0) {
        const sigInputs = document.querySelectorAll('.sig-text');
        sigInputs.forEach((el, i) => {
            if (window.savedSigTexts[i] !== undefined) {
                el.value = window.savedSigTexts[i];
            }
        });
    }

    // Restore signature images
    if (window.savedSigImages && window.savedSigImages.length > 0) {
        const containers = document.querySelectorAll('.sig-container');
        containers.forEach((container, i) => {
            const saved = window.savedSigImages[i];
            if (!saved || !saved.src) return;

            const sigBox    = container.querySelector('.sig-box');
            const img       = container.querySelector('.sig-box img');
            const deleteBtn = container.querySelector('.sig-delete');
            const iconBtn   = container.querySelector('.sig-icon-btn');

            if (!sigBox || !img) return;

            img.src              = saved.src;
            sigBox.style.display = 'block';
            sigBox.style.width   = saved.width  || '140px';
            sigBox.style.height  = saved.height || '70px';
            sigBox.style.left    = saved.left   || '10px';
            sigBox.style.top     = saved.top    || '5px';

            if (deleteBtn) deleteBtn.style.display = 'block';
            if (iconBtn)   iconBtn.style.display   = 'none';

            enableDrag(sigBox);

            if (deleteBtn) {
                deleteBtn.onclick = () => {
                    if (!confirm('Remove this signature?')) return;
                    img.src              = '';
                    sigBox.style.display = 'none';
                    sigBox.style.left    = '';
                    sigBox.style.top     = '';
                    if (iconBtn)   iconBtn.style.display   = 'flex';
                    if (deleteBtn) deleteBtn.style.display = 'none';
                };
            }
        });
    }

    document.querySelectorAll("textarea").forEach(t => {
        autoExpand(t);
        t.addEventListener("input", () => autoExpand(t));
    });

    const overlay = document.getElementById('modalOverlay');
    if (overlay) {
        overlay.addEventListener('click', function () {
            document.getElementById('reportNamePrompt').style.display = 'none';
            this.style.display = 'none';
        });
    }

    const form = document.querySelector("form");
    if (form) {
        form.addEventListener("submit", function () {
            const rows = document.querySelectorAll("#accomplishmentTable tbody tr");
            const data = [];
            rows.forEach(row => {
                const cols = row.querySelectorAll("td textarea, td input");
                data.push({
                    day:            cols[0]?.value || "",
                    accomplishment: cols[1]?.value || "",
                    description:    cols[2]?.value || ""
                });
            });
            const hidden   = document.createElement("input");
            hidden.type    = "hidden";
            hidden.name    = "accomplishments";
            hidden.value   = JSON.stringify(data);
            this.appendChild(hidden);
        });
    }
});

/* ============================================================
   TOAST NOTIFICATION
   ============================================================ */
function showToast(message, type = 'success') {
    // Remove any existing toast
    const existing = document.getElementById('saveToast');
    if (existing) existing.remove();

    const toast = document.createElement('div');
    toast.id = 'saveToast';
    toast.textContent = message;
    toast.style.cssText = `
        position: fixed;
        bottom: 28px;
        right: 28px;
        padding: 12px 20px;
        border-radius: 8px;
        font-family: 'IBM Plex Sans', Arial, sans-serif;
        font-size: 13px;
        font-weight: 600;
        color: #fff;
        background: ${type === 'error' ? '#dc3545' : '#28a745'};
        box-shadow: 0 4px 16px rgba(0,0,0,0.18);
        z-index: 99999;
        opacity: 0;
        transform: translateY(12px);
        transition: opacity 0.3s ease, transform 0.3s ease;
        pointer-events: none;
        min-width: 220px;
        text-align: center;
    `;
    document.body.appendChild(toast);

    // Animate in
    requestAnimationFrame(() => {
        requestAnimationFrame(() => {
            toast.style.opacity = '1';
            toast.style.transform = 'translateY(0)';
        });
    });

    // Animate out after delay
    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transform = 'translateY(12px)';
        setTimeout(() => toast.remove(), 350);
    }, 2000);
}

/* ============================================================
   VALIDATION
   ============================================================ */
function validateReportForm() {
    // Block save if date range is invalid
    const errorEl = document.getElementById('dateRangeError');
    if (errorEl && errorEl.style.display === 'block') {
        alert('"From" date cannot be later than "To" date. Please fix the date range before saving.');
        return false;
    }

    const weekRange = document.getElementById('weekRangeHidden')?.value.trim() || '';
    const employee  = document.querySelector('input[name="employee"]')?.value.trim() || '';
    const division  = document.querySelector('input[name="division"]')?.value.trim() || '';
    const position  = document.querySelector('input[name="position"]')?.value.trim() || '';
    const branch    = document.querySelector('input[name="branch"]')?.value.trim() || '';
    const workTask  = document.querySelector('textarea[name="work_task"]')?.value.trim() || '';

    const rows = document.querySelectorAll("#accomplishmentTable tbody tr");
    let hasAccomplishment = false;
    rows.forEach(row => {
        const day            = row.cells[0]?.querySelector('input[type="date"]')?.value.trim() || '';
        const mode           = row.cells[0]?.querySelector('.work-mode')?.value.trim() || '';
        const accomplishment = row.cells[1]?.querySelector('textarea')?.value.trim() || '';
        const description    = row.cells[2]?.querySelector('textarea')?.value.trim() || '';
        if (day || mode || accomplishment || description) hasAccomplishment = true;
    });

    if (!weekRange && !employee && !division && !position && !branch && !workTask && !hasAccomplishment) {
        alert("Cannot save empty report. Please fill in at least one field.");
        return false;
    }
    return true;
}

/* ============================================================
   SAVE REPORT
   ============================================================ */
function saveReport(event) {
    if (event) event.preventDefault();
    const form = document.getElementById('reportForm');
    if (!validateReportForm()) return;

    const reportNameInput = form.querySelector('input[name="report_name"]');
    const hasReportId     = !!window.editingReportId;
    const hasName         = reportNameInput && reportNameInput.value.trim() !== "";

    if (!hasReportId && !hasName) {
        document.getElementById('reportNamePrompt').style.display = 'block';
        document.getElementById('modalOverlay').style.display     = 'block';
        document.getElementById('newReportName').focus();
        return;
    }
    submitReport();
}

function confirmReportName() {
    const name = document.getElementById('newReportName').value.trim();
    if (!name) {
        alert('Please enter a report name.');
        document.getElementById('newReportName').focus();
        return;
    }
    const form   = document.getElementById('reportForm');
    const hidden = form.querySelector('input[name="report_name"]');
    hidden.value = name;
    document.getElementById('reportNamePrompt').style.display = 'none';
    document.getElementById('modalOverlay').style.display     = 'none';
    submitReport();
}

function submitReport() {
    const form      = document.getElementById('reportForm');
    const weekRange = document.getElementById('weekRangeHidden')?.value || '';
    const rangeStart = document.getElementById('rangeStart')?.value || '';
    const rangeEnd   = document.getElementById('rangeEnd')?.value   || '';
    const employee  = form.querySelector('input[name="employee"]').value;
    const division  = form.querySelector('input[name="division"]').value;
    const position  = form.querySelector('input[name="position"]').value;
    const branch    = form.querySelector('input[name="branch"]').value;
    const workTask  = form.querySelector('textarea[name="work_task"]').value;

    const rows            = document.querySelectorAll("#accomplishmentTable tbody tr");
    const accomplishments = [];
    rows.forEach(row => {
        accomplishments.push({
            day:            row.cells[0]?.querySelector('input[type="date"]')?.value || '',
            mode:           row.cells[0]?.querySelector('.work-mode')?.value || '',
            accomplishment: row.cells[1]?.querySelector('textarea')?.value || '',
            description:    row.cells[2]?.querySelector('textarea')?.value || ''
        });
    });

    const formData = new FormData();
    formData.append('action', 'save_report');
    if (window.editingReportId) formData.append('id', window.editingReportId);

    const reportNameInput = form.querySelector('input[name="report_name"]');
    if (reportNameInput) formData.append('report_name', reportNameInput.value);

    formData.append('week_range',      weekRange);
    formData.append('range_start',     rangeStart);
    formData.append('range_end',       rangeEnd);
    formData.append('employee',        employee);
    formData.append('division',        division);
    formData.append('position',        position);
    formData.append('branch',          branch);
    formData.append('work_task',       workTask);
    formData.append('accomplishments', JSON.stringify(accomplishments));

    // Save sig-text values (name/position under each signature)
    const sigTexts = [];
    document.querySelectorAll('.sig-text').forEach(el => sigTexts.push(el.value || ''));
    formData.append('sig_texts', JSON.stringify(sigTexts));

    // Save signature image data URLs
    const sigImages = [];
    document.querySelectorAll('.sig-container').forEach(container => {
        const img = container.querySelector('.sig-box img');
        const box = container.querySelector('.sig-box');
        if (img && img.src && img.src.startsWith('data:image')) {
            sigImages.push({
                src:    img.src,
                width:  box ? box.style.width  : '',
                height: box ? box.style.height : '',
                left:   box ? box.style.left   : '',
                top:    box ? box.style.top    : ''
            });
        } else {
            sigImages.push(null);
        }
    });
    formData.append('sig_images', JSON.stringify(sigImages));

    fetch(window.location.href, { method: 'POST', body: formData })
        .then(res => res.text())
        .then(data => {
            if (!window.editingReportId && !isNaN(data) && data.trim() !== '') {
                window.editingReportId = parseInt(data);
                showToast('✅ Report saved successfully!');
                setTimeout(() => {
                    window.location.href = window.location.pathname + '?id=' + window.editingReportId;
                }, 1200);
            } else {
                showToast('✅ Report saved successfully!');
                setTimeout(() => window.location.reload(), 1200);
            }
        })
        .catch(error => {
            console.error('Error saving report:', error);
            showToast('❌ Error saving report. Please try again.', 'error');
        });
}