(function () {
  const AUTH_STATE_KEY = "auth_state"; 
  const AUTH_READY_EVENT = "motiv8:auth-ready";
  const API_BASE = "../Login_FAQs/api";
  const POST_LOGIN_REDIRECT = "../homepage/homepage.html";

  function publishAuthState(user) {
    window.motiv8AuthUser = user || null;
    window.dispatchEvent(new CustomEvent(AUTH_READY_EVENT, {
      detail: {
        authenticated: Boolean(user),
        user: user || null,
      },
    }));
  }

  function getCurrentPage() {
    return window.location.pathname.split("/").pop() || "homepage.html";
  }

  function getAuthModeFromUrl() {
    const queryMode = new URLSearchParams(window.location.search).get("mode") || "";
    const hashMode = window.location.hash.replace("#", "");
    const mode = (queryMode || hashMode).trim().toLowerCase();
    return mode === "signup" ? "signup" : "login";
  }

  async function apiRequest(path, options = {}) {
    const response = await fetch(`${API_BASE}/${path}`, {
      credentials: "include",
      ...options,
      headers: {
        "Content-Type": "application/json",
        ...(options.headers || {}),
      },
    });

    let payload = {};
    try {
      payload = await response.json();
    } catch {
      payload = {};
    }

    if (!response.ok) {
      throw new Error(payload.error || "Request failed.");
    }

    return payload;
  }
  window.apiRequest = apiRequest;

  async function fetchSessionUser() {
    try {
      const payload = await apiRequest("me.php", { method: "GET", headers: {} });
      return payload.authenticated ? payload.user : null;
    } catch {
      return null;
    }
  }

  function setMessage(element, text, isError = false) {
    if (!element) return;
    element.textContent = text;
    if (isError) {
      element.classList.add("error");
    } else {
      element.classList.remove("error");
    }
  }

  function attachAuthTabs() {
    const loginTab = document.getElementById("tab-login");
    const signupTab = document.getElementById("tab-signup");
    const loginForm = document.getElementById("login-form");
    const signupForm = document.getElementById("signup-form");
    const authCard = document.querySelector(".auth-card");
    if (!loginTab || !signupTab || !loginForm || !signupForm) return;

    let activePanel = loginForm.classList.contains("hidden") ? signupForm : loginForm;
    let isAnimating = false;

    function setPanelWithoutAnimation(targetPanel) {
      const goingToSignup = targetPanel === signupForm;
      loginForm.classList.toggle("hidden", goingToSignup);
      signupForm.classList.toggle("hidden", !goingToSignup);
      loginTab.classList.toggle("active", !goingToSignup);
      signupTab.classList.toggle("active", goingToSignup);
      loginTab.setAttribute("aria-selected", goingToSignup ? "false" : "true");
      signupTab.setAttribute("aria-selected", goingToSignup ? "true" : "false");
      activePanel = targetPanel;
    }

    function animatePanelIn(panel, fromDirection) {
      panel.classList.remove("hidden", "panel-enter", "panel-exit", "from-left", "from-right", "to-left", "to-right");
      panel.classList.add("panel-enter", fromDirection === "left" ? "from-left" : "from-right");
      panel.addEventListener("animationend", () => panel.classList.remove("panel-enter", "from-left", "from-right"), { once: true });
    }

    function animatePanelOut(panel, toDirection, onDone) {
      panel.classList.remove("panel-enter", "panel-exit", "from-left", "from-right", "to-left", "to-right");
      panel.classList.add("panel-exit", toDirection === "left" ? "to-left" : "to-right");
      panel.addEventListener("animationend", () => {
        panel.classList.remove("panel-exit", "to-left", "to-right");
        panel.classList.add("hidden");
        onDone();
      }, { once: true });
    }

    function animateCardHeight(fromHeight, toHeight) {
      if (!authCard || Math.abs(fromHeight - toHeight) < 1) return;
      authCard.classList.add("height-animating");
      authCard.style.height = `${fromHeight}px`;
      requestAnimationFrame(() => authCard.style.height = `${toHeight}px`);
      authCard.addEventListener("transitionend", () => {
        authCard.classList.remove("height-animating");
        authCard.style.height = "";
      }, { once: true });
    }

    function switchPanel(targetPanel) {
      if (isAnimating || targetPanel === activePanel) return;
      isAnimating = true;
      const startCardHeight = authCard ? authCard.offsetHeight : 0;
      const goingToSignup = targetPanel === signupForm;
      loginTab.classList.toggle("active", !goingToSignup);
      signupTab.classList.toggle("active", goingToSignup);
      loginTab.setAttribute("aria-selected", goingToSignup ? "false" : "true");
      signupTab.setAttribute("aria-selected", goingToSignup ? "true" : "false");
      
      const outgoing = activePanel;
      const incoming = targetPanel;
      const outDirection = goingToSignup ? "left" : "right";
      const inDirection = goingToSignup ? "right" : "left";

      animatePanelOut(outgoing, outDirection, () => {
        animatePanelIn(incoming, inDirection);
        activePanel = incoming;
        requestAnimationFrame(() => animateCardHeight(startCardHeight, authCard ? authCard.offsetHeight : 0));
        isAnimating = false;
      });
    }

    loginTab.addEventListener("click", () => switchPanel(loginForm));
    signupTab.addEventListener("click", () => switchPanel(signupForm));

    if (getCurrentPage() === "login.html" && getAuthModeFromUrl() === "signup") {
      setPanelWithoutAnimation(signupForm);
    }
  }

  function attachLoginHandler() {
    const form = document.getElementById("login-form");
    const message = document.getElementById("login-message");
    if (!form || !message) return;

    form.addEventListener("submit", async (event) => {
      event.preventDefault();
      const email = document.getElementById("email").value.trim().toLowerCase();
      const password = document.getElementById("password").value;

      try {
        setMessage(message, "Logging in...");
        await apiRequest("login.php", { method: "POST", body: JSON.stringify({ email, password }) });
        setMessage(message, "Login successful. Redirecting...");
        window.location.href = POST_LOGIN_REDIRECT;
      } catch (error) {
        setMessage(message, error.message, true);
      }
    });
  }

  function attachSignupHandler() {
    const form = document.getElementById("signup-form");
    const message = document.getElementById("signup-message");
    if (!form || !message) return;

    form.addEventListener("submit", async (event) => {
      event.preventDefault();
      const username = document.getElementById("signup-name").value.trim();
      const email = document.getElementById("signup-email").value.trim().toLowerCase();
      const PhoneNum = document.getElementById("signup-phone").value.trim();
      const password = document.getElementById("signup-password").value;
      const confirmPassword = document.getElementById("signup-confirm-password").value;

      try {
        setMessage(message, "Creating account...");
        await apiRequest("register.php", { method: "POST", body: JSON.stringify({ username, email, PhoneNum, password, confirmPassword }) });
        setMessage(message, "Account created. Redirecting...");
        window.location.href = POST_LOGIN_REDIRECT;
      } catch (error) {
        setMessage(message, error.message, true);
      }
    });
  }

  function attachLogoutHandler() {
    const btn = document.getElementById("logout-btn");
    if (!btn) return;
    btn.addEventListener("click", async (event) => {
      event.preventDefault();
      try { await apiRequest("logout.php", { method: "POST" }); } catch {}
      sessionStorage.setItem(AUTH_STATE_KEY, "out");
      window.location.href = "../Login_FAQs/login.html";
    });
  }

  function attachPasswordToggle() {
    const toggleButtons = document.querySelectorAll(".toggle-password");
    toggleButtons.forEach(btn => {
      btn.addEventListener("click", () => {
        const wrapper = btn.closest(".password-wrapper");
        if (!wrapper) return;
        const input = wrapper.querySelector("input");
        const svg = btn.querySelector("svg");
        if (!input || !svg) return;
        
        if (input.type === "password") {
          input.type = "text";
          svg.innerHTML = '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line>';
        } else {
          input.type = "password";
          svg.innerHTML = '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle>';
        }
      });
    });
  }

  function renderGeminiInlineGate() {
    const chatCard = document.querySelector(".chat-card");
    if (!chatCard) return;
    chatCard.innerHTML = `
      <h1 class="gemini-title">Gemini Chat<span>Assistant</span></h1>
      <p class="chat-subtitle">Sign in to ask questions, keep your chat history, and get workout insights.</p>
      <section class="inline-auth-gate">
        <div class="inline-auth-gate-accent"></div>
        <p class="inline-auth-gate-kicker">Sign In Required</p>
        <h2>Unlock your personal <span class="inline-auth-gate-highlight">AI fitness</span> assistant</h2>
        <p class="inline-auth-gate-text">Log in to continue chatting with Gemini, keep your <span class="inline-auth-gate-emphasis">conversation history</span>, unlock <span class="inline-auth-gate-emphasis">personal workout context</span>, and get <span class="inline-auth-gate-emphasis">smarter fitness insights</span>.</p>
        <div class="inline-auth-gate-tags">
          <span class="inline-auth-gate-tag">Saved chat history</span>
          <span class="inline-auth-gate-tag">Personal workout context</span>
          <span class="inline-auth-gate-tag">Smarter fitness insights</span>
        </div>
        <div class="inline-auth-gate-actions">
          <a class="inline-auth-gate-btn" href="../Login_FAQs/login.html">Log In To Continue</a>
          <p class="inline-auth-gate-note">New here? Create an account from the same login page.</p>
        </div>
      </section>
    `;
  }

  function renderDashboardUser(user) {
    const userDisplayNode = document.getElementById("user-display");
    if (!userDisplayNode || !user) return;
    if (user.role === "admin") {
      userDisplayNode.textContent = "admin";
    } else if (user.name) {
      userDisplayNode.textContent = user.name;
    }
  }

  function injectManagedButtonStyles() {
    if (document.getElementById("nav-auth-managed-style")) return;
    const style = document.createElement("style");
    style.id = "nav-auth-managed-style";
    style.textContent = `
      .nav-cta .login-btn[data-nav-auth-managed="1"] {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-family: var(--font-main, "Segoe UI", Tahoma, Geneva, Verdana, sans-serif);
        font-size: 15px;
        font-weight: 700;
        line-height: 1;
        white-space: nowrap;
        min-width: 168px;
        min-height: 42px;
        transition: background-color 0.25s ease, color 0.25s ease, box-shadow 0.25s ease, transform 0.25s ease;
      }
    `;
    document.head.appendChild(style);
  }

  function ensureLeaderboardNavLink() {
    const nav = document.querySelector(".floating-nav");
    if (!nav) return;
    const existingLeaderboard = nav.querySelector('a[href$="leaderboard.php"]');
    if (existingLeaderboard) return;

    const leaderboardLink = document.createElement("a");
    leaderboardLink.href = "../Login_FAQs/leaderboard.php";
    leaderboardLink.className = "nav-item";
    leaderboardLink.textContent = "Leaderboard";
    nav.appendChild(leaderboardLink);
  }

  function updateNavAuthButton(user) {
    const navAuthButton = document.querySelector(".nav-cta .login-btn");
    const navCta = document.querySelector(".nav-cta");
    if (!navAuthButton || !navCta) return;

    injectManagedButtonStyles();
    navAuthButton.dataset.navAuthManaged = "1";

    if (user) {
      sessionStorage.setItem(AUTH_STATE_KEY, "in");
      navAuthButton.id = "logout-btn";
      navAuthButton.textContent = "Log Out";
      navAuthButton.setAttribute("href", "../Login_FAQs/login.html");
      
      if (user.role === 'admin' && !user.cached) {
        let adminBtn = document.getElementById("admin-nav-btn");
        if (!adminBtn) {
          adminBtn = document.createElement("a");
          adminBtn.id = "admin-nav-btn";
          adminBtn.href = "../Login_FAQs/admin_dashboard.php";
          adminBtn.className = "admin-nav-btn";
          adminBtn.title = "Admin Dashboard";
          adminBtn.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="settings-icon"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>';
          navCta.insertBefore(adminBtn, navAuthButton);
        }
      }
    } else {
      sessionStorage.setItem(AUTH_STATE_KEY, "out");
      navAuthButton.id = "";
      navAuthButton.textContent = "Log In / Sign Up";
      navAuthButton.setAttribute("href", "../Login_FAQs/login.html");
      const adminBtn = document.getElementById("admin-nav-btn");
      if (adminBtn) adminBtn.remove();
    }
  }

  function applyCachedNavAuthState() {
    const cachedState = sessionStorage.getItem(AUTH_STATE_KEY);
    if (cachedState === "in") {
      updateNavAuthButton({ cached: true });
    } else if (cachedState === "out") {
      updateNavAuthButton(null);
    }
  }

  function adaptNavForMobile() {
    const nav = document.querySelector(".floating-nav");
    const cta = document.querySelector(".nav-cta");
    if (!nav || !cta) return;
    
    if (window.innerWidth <= 820) {
      if (cta.parentNode !== nav) nav.appendChild(cta);
    } else {
      const shell = document.querySelector(".nav-shell");
      if (shell && cta.parentNode !== shell) shell.appendChild(cta);
    }
  }

  window.addEventListener("resize", adaptNavForMobile);

  function attachMobileNav() {
    const toggle = document.getElementById("menuToggle");
    const nav = document.getElementById("mainNav");
    if (!toggle || !nav) return;

    let isOpen = false;

    function setOpenState(isOpenState) {
        nav.classList.toggle("mobile-open", isOpenState);
        toggle.setAttribute("aria-expanded", isOpenState ? "true" : "false");
    }

    function toggleNav() {
        isOpen = !nav.classList.contains("mobile-open");
        setOpenState(isOpen);
    }

    toggle.addEventListener("click", function(e) {
        e.stopPropagation();
        toggleNav();
    });

    toggle.addEventListener("keydown", (event) => {
        if (event.key === "Enter" || event.key === " ") {
            event.preventDefault();
            toggleNav();
        }
    });

    nav.querySelectorAll("a").forEach((link) => {
        link.addEventListener("click", () => setOpenState(false));
    });

    window.addEventListener("resize", () => {
        if (window.innerWidth > 820) {
            setOpenState(false);
        }
    });
  }

  async function initAuth() {
    ensureLeaderboardNavLink();
    applyCachedNavAuthState();
    adaptNavForMobile();
    window.addEventListener("resize", adaptNavForMobile);
    attachMobileNav();
    
    const page = getCurrentPage();
    const user = await fetchSessionUser();

    // Pages with inline auth gates can subscribe to this single result. This
    // prevents two simultaneous /me.php requests from disagreeing while the
    // database-backed session is being read after a login redirect.
    publishAuthState(user);
    updateNavAuthButton(user);

    if (page === "login.html" && user) {
      window.location.href = POST_LOGIN_REDIRECT;
      return;
    }

    if (page === "gemini.html" && !user) {
      renderGeminiInlineGate();
      return;
    }

    attachAuthTabs();
    attachLoginHandler();
    attachSignupHandler();
    attachLogoutHandler();
    attachPasswordToggle();
    renderDashboardUser(user);
  }

  initAuth();
})();
