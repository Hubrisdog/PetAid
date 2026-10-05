// ============================================================
// js/script.js
// Simple JavaScript helper functions for PetAid
// ============================================================

function confirmDelete(message) {
    return confirm(message || "Are you sure you want to delete this item?");
}

// Optional helper to filter tables live if needed
document.addEventListener("DOMContentLoaded", function () {
    const quickSearch = document.getElementById("quick-search");
    if (quickSearch) {
        quickSearch.addEventListener("keyup", function () {
            const filter = quickSearch.value.toLowerCase();
            const rows = document.querySelectorAll(".data-table tbody tr");
            rows.forEach(function (row) {
                const text = row.textContent.toLowerCase();
                row.style.display = text.indexOf(filter) > -1 ? "" : "none";
            });
        });
    }
});
