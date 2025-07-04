document.addEventListener('DOMContentLoaded', function () {
    const form = document.querySelector('form');
    const progressBar = document.getElementById('wipc-progress-bar');
    const fill = document.getElementById('wipc-progress-bar-fill');

    if (form && progressBar && fill) {
        form.addEventListener('submit', function () {
            progressBar.style.display = 'block'; // Show progress bar
            let width = 0;
            const interval = setInterval(function () {
                if (width >= 100) {
                    clearInterval(interval); // Stop at 100%
                } else {
                    width += 5;
                    fill.style.width = width + '%'; // Increase fill width
                }
            }, 100);
        });
    }
});
