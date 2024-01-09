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
            <div class="header">
                <h4 class="ig-mfa-frontend-infobox-title">${title}</h4>
                <button class="action-close">
<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='#fff'><path d='M.293.293a1 1 0 0 1 1.414 0L8 6.586 14.293.293a1 1 0 1 1 1.414 1.414L9.414 8l6.293 6.293a1 1 0 0 1-1.414 1.414L8 9.414l-6.293 6.293a1 1 0 0 1-1.414-1.414L6.586 8 .293 1.707a1 1 0 0 1 0-1.414z'/></svg>
                </button>
            </div>
            <div class="ig-mfa-frontend-infobox-body">
                <p>${description}</p>
                <pre>${infoButton.getAttribute("data-url")}</pre>
            </div>
            <div class="ig-mfa-frontend-infobox-footer">
                <button class="btn btn-deafult action-close" name="ok">${okButton}</button>
            </div>
        </div>
        </div>
    `;

    document.body.appendChild(dialogBox);

    // Add a click event to close the dialog
    dialogBox.addEventListener("click", function (event) {
      if (event.target === dialogBox || event.target.closest(".action-close")) {
        document.body.removeChild(dialogBox);
      }
    });
  });
