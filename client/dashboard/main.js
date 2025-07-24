// main.js
// open close menu
let menuBtn = document.getElementById("menu_btn");
let closeBtn = document.getElementById("btn_close");
let menu = document.getElementById("sidebar");

menuBtn.addEventListener("click", () => {
  menu.classList.add("show_sidebar");
  document.body.style.overflow = "hidden";
});

closeBtn.addEventListener("click", () => {
  menu.classList.remove("show_sidebar");
  document.body.style.overflow = "";
});

// Toggle account number visibility
document
  .querySelector(".view_account_no")
  .addEventListener("click", function () {
    const accountNo = document.querySelector(".account_no");
    const icon = this.querySelector("i");

    accountNo.classList.toggle("visible");
    if (accountNo.classList.contains("visible")) {
      icon.classList.replace("fa-eye-slash", "fa-eye");
    } else {
      icon.classList.replace("fa-eye", "fa-eye-slash");
    }
  });

// Form prevent default submit
function mySubmitFunction(e) {
  e.preventDefault();
  return false;
}

// Notification system (same as before)
document.addEventListener("DOMContentLoaded", function () {
  // [Previous notification code remains exactly the same]
});
