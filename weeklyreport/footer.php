<script>
// Sidebar toggle
function toggleSidebar() {
    const sidebar = document.getElementById("sidebar");
    const header  = document.getElementById("header");
    const main    = document.getElementById("main");

    sidebar.classList.toggle("collapsed");
    header.classList.toggle("collapsed");
    main.classList.toggle("collapsed");

    const isCollapsed = sidebar.classList.contains("collapsed");
    localStorage.setItem("sidebarCollapsed", isCollapsed ? "true" : "false");
}

// Loader + restore sidebar state on load
window.addEventListener("load", function() {
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

// Dark Mode
const darkToggle = document.getElementById("darkToggle");

if (localStorage.getItem("darkMode") === "enabled") {
    document.body.classList.add("dark");
    darkToggle.innerHTML = "☀️ Light Mode";
}

darkToggle.addEventListener("click", function() {
    document.body.classList.toggle("dark");

    if (document.body.classList.contains("dark")) {
        localStorage.setItem("darkMode", "enabled");
        darkToggle.innerHTML = "☀️ Light Mode";
    } else {
        localStorage.setItem("darkMode", "disabled");
        darkToggle.innerHTML = "🌙 Dark Mode";
    }
});
</script>

<script>
// Profile Picture Upload
const uploadBtn  = document.getElementById('uploadBtn');
const fileInput  = document.getElementById('profileUploadInput');
const profileImg = document.getElementById('sidebarProfileImg');

uploadBtn.addEventListener('click', () => {
    fileInput.click();
});

fileInput.addEventListener('change', function() {
    const file = this.files[0];
    if (!file) return;

    const formData = new FormData();
    formData.append("profile_picture", file);
    formData.append("ajax", "profile");

    fetch("edit_account.php", {
        method: "POST",
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.status === "success") {
            profileImg.src = "uploads/" + data.profile_picture + "?" + new Date().getTime();
        } else {
            alert(data.message);
        }
    })
    .catch(err => console.error(err));
});
</script>

</body>
</html>