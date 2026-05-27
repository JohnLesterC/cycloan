document.addEventListener("DOMContentLoaded", function () {
  const viewMoreButtons = document.querySelectorAll(".view-more");
  const modal = document.getElementById("viewMoreModal");
  const closeModal = document.querySelector(".close");
  const applicationDetails = document.getElementById("applicationDetails");

  viewMoreButtons.forEach((button) => {
    button.addEventListener("click", function () {
      const applicantId = this.getAttribute("data-id");
      fetchApplicationDetails(applicantId);
      modal.style.display = "block";
    });
  });

  closeModal.onclick = function () {
    modal.style.display = "none";
  };

  window.onclick = function (event) {
    if (event.target == modal) {
      modal.style.display = "none";
    }
  };

  function fetchApplicationDetails(applicantId) {
    // Make an AJAX request to fetch application details
    const xhr = new XMLHttpRequest();
    xhr.open("GET", "fetch_application_details.php?id=" + applicantId, true);
    xhr.onload = function () {
      if (this.status === 200) {
        applicationDetails.innerHTML = this.responseText;
      }
    };
    xhr.send();
  }
});
