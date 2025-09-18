document.addEventListener("DOMContentLoaded", function () {
  const overlay = document.getElementById("age-overlay");

  // Cookieが無い場合だけ表示
  if (!document.cookie.includes("adult=1")) {
    overlay.style.display = "flex";
  }

  document.getElementById("enter-btn").addEventListener("click", function () {
    document.cookie = "adult=1; max-age=" + 60*60*24*30 + "; path=/";
    overlay.style.display = "none";
  });

  document.getElementById("exit-btn").addEventListener("click", function () {
    if (document.referrer && document.referrer !== location.href) {
      window.history.back();
    } else {
      window.location.href = "https://www.google.com/";
    }
  });
});
