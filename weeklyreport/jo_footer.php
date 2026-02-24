</div> <!-- END MAIN -->

<script>
/* Toggle Sidebar */
function toggleSidebar() {
    const sidebar = document.getElementById("sidebar");
    const header  = document.getElementById("header");
    const main    = document.getElementById("main");
    const toggleBtn = document.querySelector(".toggle-btn");

    sidebar.classList.toggle("collapsed");
    header.classList.toggle("collapsed");
    if (main) main.classList.toggle("collapsed");
    if (toggleBtn) toggleBtn.classList.toggle("collapsed");

    localStorage.setItem(
        "sidebarCollapsed",
        sidebar.classList.contains("collapsed") ? "true" : "false"
    );
}

window.addEventListener("load", function () {
    const loader = document.getElementById("loader");
    loader.style.opacity = "0";
    setTimeout(() => loader.style.display = "none", 500);

    if (localStorage.getItem("sidebarCollapsed") === "true") {
        document.getElementById("sidebar").classList.add("collapsed");
        document.getElementById("header").classList.add("collapsed");
        const main = document.getElementById("main");
        if (main) main.classList.add("collapsed");
        const toggleBtn = document.querySelector(".toggle-btn");
        if (toggleBtn) toggleBtn.classList.add("collapsed");
    }
});
/* Dark Mode */
const darkToggle = document.getElementById("darkToggle");
if (localStorage.getItem("darkMode") === "enabled") {
    document.body.classList.add("dark");
    darkToggle.innerHTML = "☀️";
}
darkToggle.addEventListener("click", function() {
    document.body.classList.toggle("dark");
    if(document.body.classList.contains("dark")){
        localStorage.setItem("darkMode","enabled");
        darkToggle.innerHTML = "☀️";
    } else {
        localStorage.setItem("darkMode","disabled");
        darkToggle.innerHTML = "🌙";
    }
});
</script>

</body>
</html>
