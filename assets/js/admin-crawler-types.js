document.getElementById('crawler-all-types').addEventListener('change', function() {
    var individual = document.getElementById('individual-post-types');
    var checkboxes = individual.querySelectorAll('.individual-type');
    if (this.checked) {
        individual.style.opacity = '0.5';
        checkboxes.forEach(function(cb) { cb.checked = false; });
    } else {
        individual.style.opacity = '1';
    }
});
