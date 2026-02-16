document.addEventListener("DOMContentLoaded", function () {
    const banner = document.getElementById("cookie-banner");
    const acceptBtn = document.getElementById("cookie-accept");
    const rejectBtn = document.getElementById("cookie-reject");

    if (!banner || !acceptBtn || !rejectBtn) {
        return;
    }

    const decision = localStorage.getItem("cookie_decision");

    if (!decision) {
        banner.style.display = "block";
    }

    acceptBtn.addEventListener("click", function () {
        localStorage.setItem("cookie_decision", "accepted");
        banner.style.display = "none";
        enableAnalytics();
    });

    rejectBtn.addEventListener("click", function () {
        localStorage.setItem("cookie_decision", "rejected");
        banner.style.display = "none";
        disableAnalytics();
    });
});

function enableAnalytics() {
    console.log("Cookies zaakceptowane – można włączyć GA / FB Pixel");

    // PRZYKŁAD: Google Analytics
    /*
    let script = document.createElement("script");
    script.src = "https://www.googletagmanager.com/gtag/js?id=G-XXXXXXX";
    document.head.appendChild(script);
    */
}

function disableAnalytics() {
    console.log("Cookies odrzucone – analityka wyłączona");
}
