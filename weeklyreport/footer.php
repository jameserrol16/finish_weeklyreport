<script>
// Immediately check sidebar state before page paint
(function() {
    const sidebarCollapsed = localStorage.getItem("sidebarCollapsed");
    if (sidebarCollapsed === "true") {
        document.body.classList.add("sidebar-collapsed"); // add a body class to apply collapsed styles
    }
})();

function toggleSidebar() {
    const sidebar = document.getElementById("sidebar");
    const header = document.getElementById("header");
    const main = document.getElementById("main");

    sidebar.classList.toggle("collapsed");
    header.classList.toggle("collapsed");
    main.classList.toggle("collapsed");

    // Save state to localStorage
    const isCollapsed = sidebar.classList.contains("collapsed");
    localStorage.setItem("sidebarCollapsed", isCollapsed ? "true" : "false");
}

// Loader
window.addEventListener("load", function() {
    const loader = document.getElementById("loader");
    if (loader) {
        loader.style.opacity = "0";
        setTimeout(() => loader.style.display = "none", 500);
    }

    const sidebarCollapsed = localStorage.getItem("sidebarCollapsed");

    if (sidebarCollapsed === "true") {
        const sidebar = document.getElementById("sidebar");
        const header = document.getElementById("header");
        const main = document.getElementById("main");

        if (sidebar) sidebar.classList.add("collapsed");
        if (header) header.classList.add("collapsed");
        if (main) main.classList.add("collapsed");
    }
});

// Dark Mode
const darkToggle = document.getElementById("darkToggle");

if (localStorage.getItem("darkMode") === "enabled") {
    document.body.classList.add("dark");
    darkToggle.innerHTML = "☀️";
}

darkToggle.addEventListener("click", function() {
    document.body.classList.toggle("dark");

    if (document.body.classList.contains("dark")) {
        localStorage.setItem("darkMode", "enabled");
        darkToggle.innerHTML = "☀️";
    } else {
        localStorage.setItem("darkMode", "disabled");
        darkToggle.innerHTML = "🌙";
    }
});
</script>
<script>
const uploadBtn = document.getElementById('uploadBtn');
const fileInput = document.getElementById('profileUploadInput');
const profileImg = document.getElementById('sidebarProfileImg');

uploadBtn.addEventListener('click', () => {
    fileInput.click(); // trigger file input
});

fileInput.addEventListener('change', function(){
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
        if(data.status === "success"){
            // Update sidebar image instantly
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
