function updateDateTime() {
  const dateTimeElement = document.getElementById("datetime");
  if (!dateTimeElement) return; // Exit if element doesn't exist

  const now = new Date();
  const options = {
    weekday: "long",
    year: "numeric",
    month: "long",
    day: "numeric",
  };
  const dayDate = now.toLocaleDateString("en-US", options);
  const time = now.toLocaleTimeString("en-US", {
    hour: "2-digit",
    minute: "2-digit",
    hour12: true,
  });
  dateTimeElement.innerHTML = `${dayDate} <span class="time">${time}</span>`;
}

// Update immediately and every second
// Wait for DOM to be ready
if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", function () {
    updateDateTime();
    setInterval(updateDateTime, 1000);
  });
} else {
  updateDateTime();
  setInterval(updateDateTime, 1000);
}
