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


  function openSUSModal() { 

    window.open("https://docs.google.com/forms/d/e/1FAIpQLScN5oNctxkFbEYmKcboImZsmEgrApA8KCT_VX4_fRTDN48WMw/viewform?usp=publish-editor", "_blank"); 

} 


//PROFILE
function openProfile() { 
    window.location.href = "../html/profile.html"; 
} 


