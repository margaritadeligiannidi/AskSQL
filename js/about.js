document.addEventListener("DOMContentLoaded", async () => {

    redirectToLoginIfUnauthenticated();

    try {

        const data = await apiFetch("../api/profile.php");

        if (data && data.username) {

            const el =
                document.getElementById("navbarUsername");

            if (el) {
                el.innerText = data.username;
            }
        }

    } catch (e) {
        console.error(e);
    }

});