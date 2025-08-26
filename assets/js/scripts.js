document.addEventListener('DOMContentLoaded', function () {
    const modal = document.getElementById("snappbox-setup-modal");
    const closeButtons = modal.querySelectorAll(".snappbox-close");
    const nextButtons = modal.querySelectorAll(".snappbox-next");
    const slides = modal.querySelectorAll(".snappbox-slide");
    const guide = document.getElementById("guide");
    const dates = document.getElementById("dates");
    let currentSlide = 0;
    document.getElementById('snappbox-launch-modal').addEventListener('click', function (e) {
        e.preventDefault();
        modal.style.display = "block";
        guide.style.display = "block";
        dates.style.display = "none";
        currentSlide = 0;
        showSlide(currentSlide);
    });
    document.getElementById('snappbox-launch-modal-guide').addEventListener('click', function (e) {
        e.preventDefault();
        modal.style.display = "block";
        guide.style.display = "none";
        dates.style.display = "block";
        
    });//
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

    if (!sessionStorage.getItem('snappboxModalSeen')) {
        modal.style.display = "block";
        dates.style.display = "none";
        sessionStorage.setItem('snappboxModalSeen', 'true');
    }

    showSlide(currentSlide);
});


