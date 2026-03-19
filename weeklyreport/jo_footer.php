<script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.js"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.css">

<!-- Sidebar Crop Modal -->
<div id="sidebarCropModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.8);z-index:199999;align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:14px;padding:24px;max-width:460px;width:94%;text-align:center;">
        <h3 style="margin:0 0 14px;font-size:15px;">✂️ Crop Your Photo</h3>
        <div style="max-height:320px;overflow:hidden;border-radius:8px;">
            <img id="sidebarCropImage" style="max-width:100%;display:block;">
        </div>
        <div style="display:flex;gap:10px;margin-top:16px;justify-content:center;">
            <button type="button" onclick="applySidebarCrop()"
                style="padding:10px 22px;background:#001f3f;color:#fff;border:none;border-radius:8px;cursor:pointer;font-size:14px;font-weight:600;">
                ✅ Apply Crop
            </button>
            <button type="button" onclick="cancelSidebarCrop()"
                style="padding:10px 22px;background:#ccc;color:#333;border:none;border-radius:8px;cursor:pointer;font-size:14px;">
                Cancel
            </button>
        </div>
    </div>
</div>

<script>
// ── Sidebar Toggle ──
function toggleSidebar() {
    const sidebar     = document.getElementById("sidebar");
    const header      = document.getElementById("header");
    const main        = document.getElementById("main");

    sidebar.classList.toggle("collapsed");
    header.classList.toggle("collapsed");
    if (main) main.classList.toggle("collapsed");

    const isCollapsed = sidebar.classList.contains("collapsed");
    localStorage.setItem("sidebarCollapsed", isCollapsed ? "true" : "false");
}

// ── Loader + Restore Sidebar State ──
window.addEventListener("load", function () {
    const loader = document.getElementById("loader");
    if (loader) {
        loader.style.opacity = "0";
        setTimeout(() => loader.style.display = "none", 500);
    }

    if (localStorage.getItem("sidebarCollapsed") === "true") {
        const sidebar = document.getElementById("sidebar");
        const header  = document.getElementById("header");
        const main    = document.getElementById("main");
        if (sidebar) sidebar.classList.add("collapsed");
        if (header)  header.classList.add("collapsed");
        if (main)    main.classList.add("collapsed");
    }
});

// ── Dark Mode ──
const darkToggle = document.getElementById("darkToggle");

if (localStorage.getItem("darkMode") === "enabled") {
    document.body.classList.add("dark");
    if (darkToggle) darkToggle.innerHTML = "☀️ Light Mode";
}

if (darkToggle) {
    darkToggle.addEventListener("click", function () {
        document.body.classList.toggle("dark");
        if (document.body.classList.contains("dark")) {
            localStorage.setItem("darkMode", "enabled");
            darkToggle.innerHTML = "☀️ Light Mode";
        } else {
            localStorage.setItem("darkMode", "disabled");
            darkToggle.innerHTML = "🌙 Dark Mode";
        }
    });
}

// ── Profile Menu ──
let sidebarCameraStream = null;

function openProfileMenu() {
    const menu = document.getElementById('profileMenu');
    menu.style.display = menu.style.display === 'none' || menu.style.display === '' ? 'block' : 'none';
}

function closeProfileMenu() {
    const menu = document.getElementById('profileMenu');
    if (menu) menu.style.display = 'none';
}

// Close menu if clicking outside
document.addEventListener('click', function (e) {
    const menu = document.getElementById('profileMenu');
    const btn  = document.querySelector('.profile-upload-btn');
    if (menu && btn && !menu.contains(e.target) && !btn.contains(e.target)) {
        closeProfileMenu();
    }
});

// ── Camera ──
function openSidebarCamera() {
    closeProfileMenu();
    const modal = document.getElementById('sidebarCameraModal');
    modal.style.display = 'flex';
    navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user' }, audio: false })
        .then(stream => {
            sidebarCameraStream = stream;
            document.getElementById('sidebarCameraFeed').srcObject = stream;
        })
        .catch(err => {
            alert('Camera access denied or not available.\n' + err.message);
            modal.style.display = 'none';
        });
}

function captureSidebarPhoto() {
    const video  = document.getElementById('sidebarCameraFeed');
    const canvas = document.getElementById('sidebarCameraCanvas');
    canvas.width  = video.videoWidth;
    canvas.height = video.videoHeight;
    canvas.getContext('2d').drawImage(video, 0, 0);
    closeSidebarCamera();
    // Open crop modal with captured photo
    openSidebarCropModal(canvas.toDataURL('image/png'));
}

function closeSidebarCamera() {
    const modal = document.getElementById('sidebarCameraModal');
    if (modal) modal.style.display = 'none';
    if (sidebarCameraStream) {
        sidebarCameraStream.getTracks().forEach(t => t.stop());
        sidebarCameraStream = null;
    }
}

// ── Sidebar Crop ──
let sidebarCropperInstance = null;

function openSidebarCropModal(src) {
    const modal = document.getElementById('sidebarCropModal');
    const img   = document.getElementById('sidebarCropImage');
    img.src = src;
    modal.style.display = 'flex';
    if (sidebarCropperInstance) { sidebarCropperInstance.destroy(); sidebarCropperInstance = null; }
    setTimeout(() => {
        sidebarCropperInstance = new Cropper(img, {
            aspectRatio: 1,
            viewMode: 1,
            movable: true,
            zoomable: true,
            scalable: false,
            background: false,
            autoCropArea: 0.8,
        });
    }, 100);
}

function applySidebarCrop() {
    if (!sidebarCropperInstance) return;
    const canvas  = sidebarCropperInstance.getCroppedCanvas({ width: 300, height: 300 });
    const dataURL = canvas.toDataURL('image/jpeg', 0.9);
    document.getElementById('sidebarProfileImg').src = dataURL;
    cancelSidebarCrop();
    uploadCroppedSidebar(dataURL);
}

function cancelSidebarCrop() {
    document.getElementById('sidebarCropModal').style.display = 'none';
    if (sidebarCropperInstance) { sidebarCropperInstance.destroy(); sidebarCropperInstance = null; }
}

function uploadCroppedSidebar(dataURL) {
    const formData = new FormData();
    formData.append('ajax', 'profile');
    formData.append('cropped_image', dataURL);
    formData.append('user_id', '<?= (int)$_SESSION['user_id'] ?>');

    fetch('jo_account.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (data.status === 'success') {
                document.getElementById('sidebarProfileImg').src =
                    'uploads/' + data.profile_picture + '?' + Date.now();
            } else {
                alert(data.message || 'Upload failed.');
            }
        })
        .catch(() => alert('Upload error. Please try again.'));
}

// ── File Picker Upload — opens crop modal ──
document.getElementById('profileUploadInput').addEventListener('change', function () {
    const file = this.files[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = e => openSidebarCropModal(e.target.result);
    reader.readAsDataURL(file);
});
</script>

</body>
</html>