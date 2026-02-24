/* ROWS */
function addRow() {
    const tbody = document.querySelector("#accomplishmentTable tbody");
    const colCount = document.querySelectorAll("#headerRow th").length;
    const tr = document.createElement("tr");

    for (let i = 0; i < colCount; i++) {
        const td = document.createElement("td");
        if (i === 0) {
            td.innerHTML = `
<input type="date" style="width:100%;"><br>
<input type="text" class="work-mode" placeholder="On-site / WFH" style="text-align:center; width:100%;" />`;
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
}

function removeLastRow() {
    const tbody = document.querySelector("#accomplishmentTable tbody");
    if (tbody.rows.length > 0) tbody.deleteRow(-1);
}

/* COLUMN ADD/REMOVE */
function addColumn(btn) {
    const index = btn.closest("th").cellIndex;
    const name = prompt("Column name:");
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
        const td = document.createElement("td");
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

/* SIGNATURES */
function loadSignature(input) {
    const container = input.closest(".sig-container");
    const sigBox = container.querySelector(".sig-box");
    const img = sigBox.querySelector("img");
    const deleteBtn = sigBox.querySelector(".sig-delete");

    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => {
            img.src = e.target.result;
            sigBox.style.display = "block";
            sigBox.style.width = "120px";
            sigBox.style.height = "60px";
            sigBox.style.left = (container.clientWidth - 120) / 2 + "px";
            sigBox.style.top = (container.clientHeight - 60) / 2 + "px";
            enableDrag(sigBox);
            deleteBtn.style.display = "block";
        };
        reader.readAsDataURL(input.files[0]);
        input.style.display = "none";
    }

    deleteBtn.onclick = () => {
        if (!confirm("Remove this signature?")) return;
        img.src = "";
        sigBox.style.display = "none";
        sigBox.style.transform = "translate(0,0)";
        sigBox.style.left = "";
        sigBox.style.top = "";
        input.value = "";
        input.style.display = "inline-block";
        deleteBtn.style.display = "none";
    };
}

/* PRECISE DRAGGING */
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
            el.style.width = (startW + e.clientX - startX) + "px";
            el.style.height = (startH + e.clientY - startY) + "px";
        }
    });

    document.addEventListener("mouseup", () => {
        if (dragging) {
            const parent = el.parentElement;
            const rect = el.getBoundingClientRect();
            const parentRect = parent.getBoundingClientRect();
            const leftPx = rect.left - parentRect.left;
            const topPx  = rect.top  - parentRect.top;
            el.style.left = leftPx + "px";
            el.style.top  = topPx  + "px";
            el.dataset.leftPct = (leftPx / parentRect.width  * 100).toFixed(4);
            el.dataset.topPct  = (topPx  / parentRect.height * 100).toFixed(4);
            el.style.transform = "none";
            x = 0;
            y = 0;
        }
        dragging = false;
        resizing = false;
    });

    resizer.addEventListener("mousedown", e => {
        resizing = true;
        startX = e.clientX;
        startY = e.clientY;
        startW = el.offsetWidth;
        startH = el.offsetHeight;
        e.stopPropagation();
        e.preventDefault();
    });
}

/* EXPORT PDF */
async function exportPDF() {
    try {
        const { jsPDF } = window.jspdf;

        // Step 1: Switch to preview mode if not already
        const wasPreview = isPreview;
        if (!isPreview) {
            togglePreview();
        }

        // Step 2: Temporarily hide all UI-only elements ON the live page
        const uiSelectors = ".table-actions, .top-right, .col-controls, .sig-resizer, .sig-delete, .signature-upload";
        const hiddenEls = [];
        reportPage.querySelectorAll(uiSelectors).forEach(el => {
            if (el.style.display !== "none") {
                el.style.display = "none";
                hiddenEls.push(el);
            }
        });

        // Also hide the box/shadow/border-radius so it looks like a clean document
        const origBoxShadow  = reportPage.style.boxShadow;
        const origBorderRadius = reportPage.style.borderRadius;
        const origBorder     = reportPage.style.border;
        reportPage.style.boxShadow   = "none";
        reportPage.style.borderRadius = "0";
        reportPage.style.border      = "none";

        // Step 3: Small delay for DOM paint
        await new Promise(r => setTimeout(r, 120));

        // Step 4: Capture the LIVE page at its real rendered size
        const canvas = await html2canvas(reportPage, {
    scale: 2,
    useCORS: true,
    backgroundColor: "#ffffff",
    scrollX: 0,
    scrollY: -window.scrollY,
  onclone: (clonedDoc) => {
    // Fix signature containers
    clonedDoc.querySelectorAll('.signature-table td.signature').forEach(td => {
        td.style.overflow = 'visible';
        td.style.height = 'auto';
        td.style.paddingBottom = '20px';
    });
    clonedDoc.querySelectorAll('.sig-container').forEach(c => {
        c.style.overflow = 'visible';
        c.style.marginBottom = '8px';
    });
    clonedDoc.querySelectorAll('.sig-text').forEach(el => {
        el.style.display = 'block';
        el.style.visibility = 'visible';
        el.style.height = 'auto';
        el.style.overflow = 'visible';
    });

    // ✅ THE MAIN FIX: Replace all inputs/textareas with divs
    clonedDoc.querySelectorAll('input:not([type="file"]):not([type="hidden"]), textarea').forEach(el => {
        const div = clonedDoc.createElement('div');

        // Copy the value as text content
        div.textContent = el.value || '';

        // Copy computed styles
        const computed = window.getComputedStyle(el);
        div.style.cssText = el.style.cssText;
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

        // Preserve text-align (center for day column)
        div.style.textAlign = computed.textAlign;

        el.parentNode.replaceChild(div, el);
    });
}
});

        // Step 5: Restore hidden elements & styles
        hiddenEls.forEach(el => el.style.display = "");
        reportPage.style.boxShadow    = origBoxShadow;
        reportPage.style.borderRadius = origBorderRadius;
        reportPage.style.border       = origBorder;

        // Step 6: Restore preview state if needed
        if (!wasPreview) {
            togglePreview();
        }

        // Step 7: Build PDF from the captured canvas
       const imgData = canvas.toDataURL("image/png");
const pdf = new jsPDF("p", "mm", "a4");

const pageW = 210;
const pageH = 297; // A4 height in mm
const imgW = pageW;
const imgH = canvas.height * pageW / canvas.width; // total rendered height in mm

const totalPages = Math.ceil(imgH / pageH);

for (let i = 0; i < totalPages; i++) {
    if (i > 0) pdf.addPage();

    // Calculate the slice of the canvas for this page
    const srcY = i * pageH * (canvas.width / pageW); // px offset in canvas
    const srcH = pageH * (canvas.width / pageW);     // px height per page

    // Create a slice canvas
    const sliceCanvas = document.createElement("canvas");
    sliceCanvas.width = canvas.width;
    sliceCanvas.height = Math.min(srcH, canvas.height - srcY);
    const ctx = sliceCanvas.getContext("2d");
    ctx.drawImage(canvas, 0, srcY, canvas.width, sliceCanvas.height, 0, 0, canvas.width, sliceCanvas.height);

    const sliceData = sliceCanvas.toDataURL("image/png");
    const sliceH = sliceCanvas.height * pageW / canvas.width;
    pdf.addImage(sliceData, "PNG", 0, 0, imgW, sliceH);
}

pdf.save("Weekly_Accomplishment_Report.pdf");
    } catch (err) {
        alert("PDF export failed: " + err.message);
        console.error("exportPDF error:", err);
        if (!wasPreview && isPreview) togglePreview();
    }
}

/* PREVIEW TOGGLE */
let isPreview = false;
let storedElements = [];

function togglePreview() {
    isPreview = !isPreview;
    reportPage.classList.toggle("preview-mode");

    if (isPreview) {
        previewBtn.textContent = "✏ Exit Preview";
        storedElements = [];

        reportPage.querySelectorAll(".sig-text").forEach(el => {
            if (el.value.trim()) {
                el.setAttribute("data-has-value", "true");
            } else {
                el.removeAttribute("data-has-value");
            }
        });

        reportPage.querySelectorAll(".sig-delete").forEach(el => el.style.display = "none");
        reportPage.querySelectorAll(".signature-upload").forEach(el => el.style.display = "none");

        reportPage.querySelectorAll("input, textarea, select").forEach(el => {
            if (el.type === "file" || el.closest(".signature")) return;
            if (!el.value || !el.value.trim()) {
                el.style.display = "none";
            }
        });

    } else {
        previewBtn.textContent = "👁 Preview";

        reportPage.querySelectorAll(".sig-box").forEach(box => {
            const img = box.querySelector("img");
            const deleteBtn = box.querySelector(".sig-delete");
            if (img && img.src && !img.src.startsWith("data:,") && img.src !== window.location.href) {
                deleteBtn.style.display = "block";
            } else {
                deleteBtn.style.display = "none";
            }
        });

       reportPage.querySelectorAll('.sig-container').forEach(container => {
    const uploadInput = container.querySelector('input.signature-upload');
    const sigBox = container.querySelector('.sig-box');
    const sigImg = sigBox ? sigBox.querySelector('img') : null;
    const hasSig = sigImg && sigImg.src && sigImg.src.startsWith("data:image");
    // Only show upload if no signature AND input is not disabled (readonly mode)
    if (uploadInput && !uploadInput.disabled) {
        uploadInput.style.display = hasSig ? "none" : "inline-block";
    }
    if (sigBox && hasSig) {
        sigBox.style.display = "block";
    }
});

        reportPage.querySelectorAll(".sig-text").forEach(el => el.style.display = "block");

        reportPage.querySelectorAll("input, textarea, select").forEach(el => {
            el.style.display = "";
        });
    }
}

/* AUTO EXPAND TEXTAREA */
function autoExpand(el) {
    el.style.height = "auto";
    el.style.height = el.scrollHeight + "px";
}

/* DOM READY */
document.addEventListener("DOMContentLoaded", () => {
    addRow();

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

    // ✅ Move form listener here so DOM is guaranteed to exist
    const form = document.querySelector("form");
    if (form) {
        form.addEventListener("submit", function () {
            const rows = document.querySelectorAll("#accomplishmentTable tbody tr");
            let data = [];
            rows.forEach(row => {
                let cols = row.querySelectorAll("td textarea, td input");
                data.push({
                    day: cols[0]?.value || "",
                    accomplishment: cols[1]?.value || "",
                    description: cols[2]?.value || ""
                });
            });
            let hidden = document.createElement("input");
            hidden.type = "hidden";
            hidden.name = "accomplishments";
            hidden.value = JSON.stringify(data);
            this.appendChild(hidden);
        });
    }
});

/* VALIDATION */
function validateReportForm() {
    const weekRange = document.querySelector('input[name="week_range"]').value.trim();
    const employee  = document.querySelector('input[name="employee"]')?.value.trim() || '';
    const division  = document.querySelector('input[name="division"]')?.value.trim() || '';
    const position  = document.querySelector('input[name="position"]').value.trim();
    const branch    = document.querySelector('input[name="branch"]').value.trim();
    const workTask  = document.querySelector('textarea[name="work_task"]').value.trim();

    const rows = document.querySelectorAll("#accomplishmentTable tbody tr");
    let hasAccomplishment = false;
    rows.forEach(row => {
        const day           = row.cells[0]?.querySelector('input[type="date"]')?.value.trim() || '';
        const mode          = row.cells[0]?.querySelector('.work-mode')?.value.trim() || '';
        const accomplishment = row.cells[1]?.querySelector('textarea')?.value.trim() || '';
        const description   = row.cells[2]?.querySelector('textarea')?.value.trim() || '';
        if (day || mode || accomplishment || description) hasAccomplishment = true;
    });

    if (!weekRange && !employee && !division && !position && !branch && !workTask && !hasAccomplishment) {
        alert("Cannot save empty report. Please fill in at least one field.");
        return false;
    }
    return true;
}

/* SAVE REPORT */
function saveReport(event) {
    if (event) event.preventDefault();
    const form = document.getElementById('reportForm');
    if (!validateReportForm()) return;

    const reportNameInput = form.querySelector('input[name="report_name"]');
    const hasReportId = !!window.editingReportId;
    const hasName = reportNameInput && reportNameInput.value.trim() !== "";

    if (!hasReportId && !hasName) {
        document.getElementById('reportNamePrompt').style.display = 'block';
        document.getElementById('modalOverlay').style.display = 'block';
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
    const form = document.getElementById('reportForm');
    const hidden = form.querySelector('input[name="report_name"]');
    hidden.value = name;
    document.getElementById('reportNamePrompt').style.display = 'none';
    document.getElementById('modalOverlay').style.display = 'none';
    submitReport();
}

function submitReport() {
    const form = document.getElementById('reportForm');
    const weekRange = form.querySelector('input[name="week_range"]').value;
    const employee  = form.querySelector('input[name="employee"]').value;
    const division  = form.querySelector('input[name="division"]').value;
    const position  = form.querySelector('input[name="position"]').value;
    const branch    = form.querySelector('input[name="branch"]').value;
    const workTask  = form.querySelector('textarea[name="work_task"]').value;

    const rows = document.querySelectorAll("#accomplishmentTable tbody tr");
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

    formData.append('week_range', weekRange);
    formData.append('employee',   employee);
    formData.append('division',   division);
    formData.append('position',   position);
    formData.append('branch',     branch);
    formData.append('work_task',  workTask);
    formData.append('accomplishments', JSON.stringify(accomplishments));

    fetch(window.location.href, { method: 'POST', body: formData })
        .then(res => res.text())
        .then(data => {
            if (!window.editingReportId && !isNaN(data) && data.trim() !== '') {
                window.editingReportId = parseInt(data);
            }
            alert('Report saved successfully.');
        })
        .catch(error => {
            console.error('Error saving report:', error);
            alert('Error saving report. Please try again.');
        });
}