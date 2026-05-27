// FAQ Modal and Accordion Functionality
document.addEventListener("DOMContentLoaded", function () {
  const faqIcon = document.querySelector(".faq-icon");
  const faqModal = document.getElementById("faqModal");
  const faqClose = document.querySelector(".faq-close");
  const faqQuestions = document.querySelectorAll(".faq-question");

  // Toggle FAQ Modal
  faqIcon.addEventListener("click", function () {
    faqModal.style.display = "flex";
  });

  faqClose.addEventListener("click", function () {
    faqModal.style.display = "none";
  });

  // Close modal when clicking outside
  faqModal.addEventListener("click", function (event) {
    if (event.target === faqModal) {
      faqModal.style.display = "none";
    }
  });

  // FAQ Accordion
  faqQuestions.forEach((question) => {
    question.addEventListener("click", function () {
      const answer = this.nextElementSibling;
      const isActive = answer.classList.contains("active");

      // Close all other answers
      document.querySelectorAll(".faq-answer").forEach((ans) => {
        ans.classList.remove("active");
      });
      document.querySelectorAll(".faq-question").forEach((q) => {
        q.classList.remove("active");
      });

      // Toggle the clicked answer
      if (!isActive) {
        answer.classList.add("active");
        this.classList.add("active");
      }
    });
  });
});

// Slideshow Functionality
let slideIndex = 0;
showSlides();

function showSlides() {
  let i;
  let slides = document.getElementsByClassName("slide");
  let dots = document.getElementsByClassName("dot");
  for (i = 0; i < slides.length; i++) {
    slides[i].style.display = "none";
  }
  slideIndex++;
  if (slideIndex > slides.length) {
    slideIndex = 1;
  }
  for (i = 0; i < dots.length; i++) {
    dots[i].className = dots[i].className.replace(" active", "");
  }
  slides[slideIndex - 1].style.display = "block";
  dots[slideIndex - 1].className += " active";
  setTimeout(showSlides, 5000); // Change slide every 5 seconds
}

function currentSlide(n) {
  slideIndex = n;
  showSlides();
}
