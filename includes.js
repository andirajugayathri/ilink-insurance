document.addEventListener("DOMContentLoaded", function () {
  fetch("navbar.html")
    .then((response) => {
      if (!response.ok) throw new Error("Failed to load navbar.html");
      return response.text();
    })
    .then((data) => {
      const navbar = document.getElementById("navbar");
      if (navbar) {
        navbar.innerHTML = data;
        if (typeof highlightActiveLink === "function") {
          highlightActiveLink();
        }
      } else {
        console.error("Element with ID 'navbar' not found.");
      }
    })
    .catch((err) => console.error(err));

  fetch("footer.html")
    .then((response) => {
      if (!response.ok) throw new Error("Failed to load footer.html");
      return response.text();
    })
    .then((data) => {
      const footer = document.getElementById("footer");
      if (footer) {
        footer.innerHTML = data;
      } else {
        console.error("Element with ID 'footer' not found.");
      }
    })
    .catch((err) => console.error(err));
});

document.addEventListener("DOMContentLoaded", function () {
  const forms = document.querySelectorAll("form");
  forms.forEach((form) => {
    form.addEventListener("submit", function (event) {
      // Find the submit button within the form
      // Look for <button type="submit"> or <input type="submit"> or standard <button> inside form (defaults to submit)
      let submitBtn = form.querySelector(
        "button[type='submit'], input[type='submit']"
      );

      // If no explicit submit button found, look for any button which might be the submit trigger
      if (!submitBtn) {
        submitBtn = form.querySelector("button");
      }

      if (submitBtn) {
        submitBtn.disabled = true;

        // Save original text to restore if needed (though we don't restore on simple submit)
        // Changing text
        if (submitBtn.tagName === "BUTTON") {
          submitBtn.innerText = "Submitting...";
        } else if (submitBtn.tagName === "INPUT") {
          submitBtn.value = "Submitting...";
        }

        submitBtn.style.cursor = "not-allowed";
        submitBtn.style.opacity = "0.7";
      }
    });
  });
});
