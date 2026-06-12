function showWorkout(id) {
    const details = document.getElementById(`workout-${id}`);
    const toggleButton = document.querySelector(`[aria-controls="workout-${id}"]`);

    if (!details || !toggleButton) {
        return;
    }

    const isOpen = details.classList.toggle("open");
    toggleButton.setAttribute("aria-expanded", isOpen ? "true" : "false");
    toggleButton.textContent = isOpen ? "Hide details" : "View details";
}

function deleteWorkout(id, event) {
    event.stopPropagation();

    if (confirm("Are you sure you want to delete this workout?")) {
        window.location.href = `deleteWorkout.php?id=${id}`;
    }
}

document.addEventListener("DOMContentLoaded", () => {
    const myBtn = document.getElementById("show-my");
    const friendBtn = document.getElementById("show-friends");
    const mySection = document.getElementById("my-workouts");
    const friendSection = document.getElementById("friends-workouts");
    const params = new URLSearchParams(window.location.search);

    if (!myBtn || !friendBtn || !mySection || !friendSection) {
        return;
    }

    function setActiveView(view) {
        const showFriends = view === "friends";

        mySection.hidden = showFriends;
        friendSection.hidden = !showFriends;

        myBtn.classList.toggle("active", !showFriends);
        friendBtn.classList.toggle("active", showFriends);

        const url = new URL(window.location.href);
        url.searchParams.set("view", showFriends ? "friends" : "my");
        window.history.replaceState({}, "", url);
    }

    myBtn.addEventListener("click", () => setActiveView("my"));
    friendBtn.addEventListener("click", () => setActiveView("friends"));

    setActiveView(params.get("view") === "friends" ? "friends" : "my");
});
