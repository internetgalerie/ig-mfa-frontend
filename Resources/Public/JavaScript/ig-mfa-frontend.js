document.querySelectorAll("[data-mfa-dismiss]").forEach(function (button) {
  button.addEventListener("click", function () {
    var dismissClass = button.getAttribute("data-mfa-dismiss");
    var el = button.closest("." + dismissClass);
    if (el) {
      el.style.display = "none";
    }
  });
});

// Get the button element
var infoButton = document.querySelector("typo3-mfa-totp-url-info-button");

// Add a click event listener to the button
document
  .querySelector("typo3-mfa-totp-url-info-button")
  ?.addEventListener("click", function (event) {
    var infoButton = this;
    var title = infoButton.getAttribute("data-title");
    var description = infoButton.getAttribute("data-description");
    var okButton = infoButton.getAttribute("data-button-ok");

    // Create a popup container
    var dialogBox = document.createElement("div");
    dialogBox.className = "ig-mfa-frontend-modal-dialog";

    // <core:icon identifier="actions-close" size="small"></core:icon>
    dialogBox.innerHTML = `
  <div class="ig-mfa-frontend-infobox" aria-modal="true" role="dialog">
        <div class="ig-mfa-frontend-infobox-content">
            <div class="ig-mfa-frontend-infobox-header">
                <h4 class="ig-mfa-frontend-infobox-title">${title}</h4>
                <button class="ig-mfa-frontend-infobox-close">
                </button>
            </div>
            <div class="ig-mfa-frontend-infobox-body">
                <p>${description}</p>
                <pre>${infoButton.getAttribute("data-url")}</pre>
            </div>
            <div class="ig-mfa-frontend-infobox-footer">
                <button class="ig-mfa-frontend-infobox-close btn btn-deafult" name="ok">${okButton}</button>
            </div>
        </div>
        </div>
    `;

    document.body.appendChild(dialogBox);

    // Add a click event to close the dialog
    dialogBox.addEventListener("click", function (event) {
      if (
        event.target === dialogBox ||
        event.target.classList.contains("ig-mfa-frontend-infobox-close")
      ) {
        document.body.removeChild(dialogBox);
      }
    });
  });
