document.addEventListener('DOMContentLoaded', function () {
    const modal = document.getElementById("snappbox-setup-modal");
    const closeButtons = modal.querySelectorAll(".snappbox-close");
    const nextButtons = modal.querySelectorAll(".snappbox-next");
    const slides = modal.querySelectorAll(".snappbox-slide");
    let currentSlide = 0;
    document.getElementById('snappbox-launch-modal').addEventListener('click', function (e) {
        e.preventDefault();
        modal.style.display = "block";
        currentSlide = 0;
        showSlide(currentSlide);
    });
    function showSlide(index) {
        slides.forEach((slide, i) => {
            slide.classList.toggle("active", i === index);
        });
    }

    nextButtons.forEach(button => {
        button.addEventListener("click", (event) => {
            if (currentSlide < slides.length - 1) {
                currentSlide++;
                showSlide(currentSlide);
            }
            event.preventDefault();
        });
    });

    closeButtons.forEach(button => {
        button.addEventListener("click", (event) => {
            modal.style.display = "none";
            event.preventDefault();
        });
    });
    // Manual trigger
    launchModalBtn.addEventListener('click', function (e) {
        e.preventDefault();
        modal.style.display = "block";
        currentSlide = 0;
        showSlide(currentSlide);
    });
    // Show modal on first load
    if (!sessionStorage.getItem('snappboxModalSeen')) {
        modal.style.display = "block";
        sessionStorage.setItem('snappboxModalSeen', 'true');
    }

    showSlide(currentSlide);
});