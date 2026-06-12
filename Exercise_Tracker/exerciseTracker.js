let exerciseNum = 0;
const exercisesContainer = document.getElementById("exercises-container");
const workoutForm = document.getElementById("workout-form");
const trackerEmptyState = document.getElementById("trackerEmptyState");
const saveButtonSlot = document.getElementById("save-button");
const workoutTypeSelect = document.getElementById("trackerWorkoutType");
const heroTitle = document.getElementById("trackerHeroTitle");
const heroTypePill = document.getElementById("trackerTypePill");
const sideTypePill = document.getElementById("trackerSideTypePill");
let exerciseCatalogPromise = null;
let currentWorkoutType = initialWorkoutType || "";

function normaliseText(value) {
    return String(value || "").trim().toLowerCase();
}

function fetchExerciseCatalog() {
    if (!exerciseCatalogPromise) {
        const filteredUrl = currentWorkoutType
            ? `getExercises.php?type=${encodeURIComponent(currentWorkoutType)}`
            : "getExercises.php";

        exerciseCatalogPromise = fetch(filteredUrl)
            .then((response) => response.json())
            .then((catalog) => {
                if (Array.isArray(catalog) && catalog.length > 0) {
                    return catalog;
                }

                if (!currentWorkoutType) {
                    return [];
                }

                return fetch("getExercises.php")
                    .then((response) => response.json())
                    .catch(() => []);
            })
            .catch((error) => {
                console.error("Error fetching exercises:", error);
                return [];
            });
    }

    return exerciseCatalogPromise;
}

function updateWorkoutTypePresentation() {
    const displayType = currentWorkoutType || "Workout Builder";

    if (heroTitle) {
        heroTitle.textContent = `${trackerUsername}'s ${displayType} workout`;
    }

    if (heroTypePill) {
        heroTypePill.textContent = displayType;
    }

    if (sideTypePill) {
        sideTypePill.textContent = displayType;
    }
}

function resetExerciseBuilder() {
    if (!exercisesContainer) return;

    exercisesContainer.innerHTML = "";
    exerciseNum = 0;
    updateBuilderEmptyState();
    updateSaveButton();
}

function updateBuilderEmptyState() {
    if (!trackerEmptyState || !exercisesContainer) return;
    trackerEmptyState.hidden = exercisesContainer.children.length > 0;
}

function saveHintMarkup() {
    return `
        <div class="tracker-save-panel is-disabled">
            <div class="tracker-save-copy">
                <span class="tracker-save-kicker">Before you save</span>
                <p class="tracker-save-hint">Add at least one set to every selected exercise before saving this workout.</p>
            </div>
            <button type="button" class="tracker-save-disabled-btn" disabled>Save Workout</button>
        </div>
    `;
}

function updateSaveButton() {
    if (!saveButtonSlot || !exercisesContainer) return;

    const exerciseCards = exercisesContainer.querySelectorAll(".exercise-card");
    let valid = !!currentWorkoutType && exerciseCards.length > 0;

    exerciseCards.forEach((card) => {
        if (card.querySelectorAll(".set-row").length === 0) {
            valid = false;
        }
    });

    saveButtonSlot.innerHTML = valid
        ? `
            <div class="tracker-save-panel">
                <div class="tracker-save-copy">
                    <span class="tracker-save-kicker">Ready to save</span>
                    <p class="tracker-save-hint">Your workout is complete. Save it now and it will appear in your workout history.</p>
                </div>
                <input type='submit' value='Save Workout'>
            </div>
        `
        : saveHintMarkup();
}

function updateExerciseIndices() {
    if (!exercisesContainer) return;

    Array.from(exercisesContainer.querySelectorAll(".exercise-card")).forEach((exerciseDiv, exerciseIndex) => {
        const orderLabel = exerciseDiv.querySelector(".exercise-card-order");
        if (orderLabel) {
            orderLabel.textContent = `Exercise ${exerciseIndex + 1}`;
        }

        const exerciseInput = exerciseDiv.querySelector(".exercise-input");
        const exerciseId = exerciseDiv.querySelector(".exercise-id");
        if (exerciseInput) {
            exerciseInput.name = `exercises[${exerciseIndex}][name]`;
        }
        if (exerciseId) {
            exerciseId.name = `exercises[${exerciseIndex}][id]`;
        }

        updateSetIndices(exerciseDiv.querySelector(".sets-container"), exerciseIndex);
    });
}

function updateSetIndices(setsContainer, exerciseIndex) {
    if (!setsContainer) return;

    setsContainer.querySelectorAll(".set-row").forEach((setDiv, setIndex) => {
        const setLabel = setDiv.querySelector(".set-number");
        if (setLabel) {
            setLabel.textContent = `Set ${setIndex + 1}`;
        }

        setDiv.querySelectorAll("input, select").forEach((inputElement) => {
            inputElement.name = inputElement.name
                .replace(/exercises\[\d+\]/, `exercises[${exerciseIndex}]`)
                .replace(/\[sets\]\[\d+\]/, `[sets][${setIndex}]`);
        });
    });
}

function buildSetMarkup(exerciseIndex, setIndex, inputType) {
    if (inputType === "reps") {
        return `
            <span class="set-number">Set ${setIndex + 1}</span>
            <div class="set-field">
                <span class="set-field-label">Reps</span>
                <input type="number" name="exercises[${exerciseIndex}][sets][${setIndex}][reps]" placeholder="10" required min="1">
            </div>
            <div class="set-field">
                <span class="set-field-label">Weight</span>
                <input type="number" name="exercises[${exerciseIndex}][sets][${setIndex}][weight]" placeholder="40" required min="1">
            </div>
            <div class="set-field">
                <span class="set-field-label">Unit</span>
                <select name="exercises[${exerciseIndex}][sets][${setIndex}][unit]" required>
                    <option value="kg">kg</option>
                    <option value="lb">lb</option>
                </select>
            </div>
            <button type="button" class="remove-set-btn">Delete Set</button>
        `;
    }

    return `
        <span class="set-number">Set ${setIndex + 1}</span>
        <div class="set-field">
            <span class="set-field-label">Duration</span>
            <input type="number" name="exercises[${exerciseIndex}][sets][${setIndex}][duration]" placeholder="30" required min="1">
        </div>
        <div class="set-field">
            <span class="set-field-label">Unit</span>
            <select name="exercises[${exerciseIndex}][sets][${setIndex}][unit]" required>
                <option value="sec">sec</option>
                <option value="min">min</option>
            </select>
        </div>
        <button type="button" class="remove-set-btn">Delete Set</button>
    `;
}

function addExercise() {
    if (!exercisesContainer) return;
    if (!currentWorkoutType) {
        alert("Choose a workout type first.");
        if (workoutTypeSelect) {
            workoutTypeSelect.focus();
        }
        return;
    }

    const exerciseIndex = exerciseNum++;
    const exerciseDiv = document.createElement("div");
    exerciseDiv.className = "exercise-card";
    exerciseDiv.innerHTML = `
        <div class="exercise-card-header">
            <span class="exercise-card-order">Exercise ${exerciseIndex + 1}</span>
            <span class="exercise-card-status">Waiting for selection</span>
        </div>
        <div class="exercise-picker">
            <label class="exercise-label">Exercise</label>
            <div class="exercise-picker-row">
                <input type="text" name="exercises[${exerciseIndex}][name]" class="exercise-input" placeholder="Search an exercise..." autocomplete="off">
                <button type="button" class="choose-btn">Browse List</button>
            </div>
            <input type="hidden" name="exercises[${exerciseIndex}][id]" class="exercise-id">
            <div class="exercise-modal" hidden>
                <ul class="exercise-list"></ul>
            </div>
        </div>
        <div class="sets-container"></div>
        <button type="button" class="add-set-btn" disabled hidden>Add Set</button>
        <button type="button" class="remove-btn">Delete Exercise</button>
    `;

    exercisesContainer.appendChild(exerciseDiv);
    updateBuilderEmptyState();

    const status = exerciseDiv.querySelector(".exercise-card-status");
    const input = exerciseDiv.querySelector(".exercise-input");
    const hiddenId = exerciseDiv.querySelector(".exercise-id");
    const chooseButton = exerciseDiv.querySelector(".choose-btn");
    const modal = exerciseDiv.querySelector(".exercise-modal");
    const list = exerciseDiv.querySelector(".exercise-list");
    const setsContainer = exerciseDiv.querySelector(".sets-container");
    const addSetButton = exerciseDiv.querySelector(".add-set-btn");
    const removeButton = exerciseDiv.querySelector(".remove-btn");

    let selectedExercise = null;

    function clearExerciseSelection(resetInput = false) {
        hiddenId.value = "";
        selectedExercise = null;
        addSetButton.disabled = true;
        addSetButton.hidden = true;
        setsContainer.innerHTML = "";
        input.classList.remove("has-selection");
        status.textContent = "Waiting for selection";
        if (resetInput) {
            input.value = "";
        }
        updateSaveButton();
    }

    function applyExerciseSelection(exercise) {
        selectedExercise = exercise;
        input.value = exercise.exercisename;
        hiddenId.value = exercise.exerciseid;
        input.classList.add("has-selection");
        setsContainer.innerHTML = "";
        addSetButton.disabled = false;
        addSetButton.hidden = false;
        status.textContent = exercise.input_type === "duration" ? "Duration based" : "Reps and weight";
        modal.hidden = true;

        if (normaliseText(exercise.exercisename) === "rest") {
            addSetRow();
            addSetButton.hidden = true;
        }

        updateSaveButton();
    }

    function renderExerciseList(query = "") {
        fetchExerciseCatalog().then((catalog) => {
            const queryText = normaliseText(query);
            const filtered = catalog.filter((exercise) =>
                normaliseText(exercise.exercisename).includes(queryText)
            );

            list.innerHTML = "";
            if (filtered.length === 0) {
                list.innerHTML = '<li class="exercise-list-empty">No matching exercises found.</li>';
                return;
            }

            filtered.forEach((exercise) => {
                const listItem = document.createElement("li");
                listItem.textContent = exercise.exercisename;
                if (selectedExercise && Number(selectedExercise.exerciseid) === Number(exercise.exerciseid)) {
                    listItem.classList.add("is-active");
                }
                listItem.addEventListener("click", () => applyExerciseSelection(exercise));
                list.appendChild(listItem);
            });
        });
    }

    function openModal() {
        modal.hidden = false;
        renderExerciseList(input.value);
    }

    function addSetRow() {
        if (!selectedExercise) {
            alert("Select an exercise first.");
            return;
        }

        const isRest = normaliseText(selectedExercise.exercisename) === "rest";
        if (isRest && setsContainer.childElementCount >= 1) {
            return;
        }

        const setIndex = setsContainer.childElementCount;
        const setDiv = document.createElement("div");
        setDiv.className = `set-row${selectedExercise.input_type === "duration" ? " duration-only" : ""}`;
        setDiv.innerHTML = buildSetMarkup(exerciseIndex, setIndex, selectedExercise.input_type);
        setsContainer.appendChild(setDiv);

        const removeSetButton = setDiv.querySelector(".remove-set-btn");
        if (removeSetButton) {
            removeSetButton.addEventListener("click", () => {
                setDiv.remove();
                updateSetIndices(setsContainer, exerciseIndex);
                updateSaveButton();
            });
        }

        updateSaveButton();
    }

    chooseButton.addEventListener("click", (event) => {
        event.preventDefault();
        if (modal.hidden) {
            openModal();
            input.focus();
            return;
        }
        modal.hidden = true;
    });

    input.addEventListener("focus", openModal);
    input.addEventListener("input", () => {
        clearExerciseSelection(false);
        openModal();
    });

    addSetButton.addEventListener("click", addSetRow);

    removeButton.addEventListener("click", () => {
        exerciseDiv.remove();
        exerciseNum = Math.max(0, exerciseNum - 1);
        updateExerciseIndices();
        updateBuilderEmptyState();
        updateSaveButton();
    });

    document.addEventListener("click", (event) => {
        if (!exerciseDiv.contains(event.target)) {
            modal.hidden = true;
        }
    });

    updateSaveButton();
}

if (workoutForm && exercisesContainer) {
    workoutForm.addEventListener("submit", (event) => {
        if (!currentWorkoutType) {
            alert("Please choose a workout type.");
            event.preventDefault();
            if (workoutTypeSelect) {
                workoutTypeSelect.focus();
            }
            return;
        }

        const exerciseCards = exercisesContainer.querySelectorAll(".exercise-card");
        if (exerciseCards.length === 0) {
            alert("Please add at least one exercise.");
            event.preventDefault();
            return;
        }

        for (let i = 0; i < exerciseCards.length; i += 1) {
            const currentExercise = exerciseCards[i];
            const selectedId = currentExercise.querySelector(".exercise-id")?.value || "";
            if (!selectedId) {
                alert(`Exercise ${i + 1} has not been selected yet.`);
                event.preventDefault();
                return;
            }

            if (currentExercise.querySelectorAll(".set-row").length === 0) {
                alert(`Exercise ${i + 1} must have at least one set.`);
                event.preventDefault();
                return;
            }
        }
    });
}

if (workoutTypeSelect) {
    workoutTypeSelect.addEventListener("change", () => {
        const nextType = workoutTypeSelect.value;
        if (nextType === currentWorkoutType) {
            return;
        }

        const hasExercises = exercisesContainer && exercisesContainer.children.length > 0;
        if (hasExercises && !confirm("Changing workout type will clear the exercises you already added. Continue?")) {
            workoutTypeSelect.value = currentWorkoutType;
            return;
        }

        currentWorkoutType = nextType;
        exerciseCatalogPromise = null;
        resetExerciseBuilder();
        updateWorkoutTypePresentation();
    });
}

updateWorkoutTypePresentation();
updateBuilderEmptyState();
updateSaveButton();
