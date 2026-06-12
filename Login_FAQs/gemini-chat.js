(function () {
  const chatForm = document.getElementById("chat-form");
  const promptInput = document.getElementById("chat-prompt");
  const chatLog = document.getElementById("chat-log");
  const statusNode = document.getElementById("chat-status");
  const clearBtn = document.getElementById("clear-chat");

  if (!chatForm || !promptInput || !chatLog || !statusNode || !clearBtn) {
    return;
  }

  const conversation = [];
  const MAX_HISTORY_MESSAGES = 12;
  const MAX_STORED_MESSAGES = 60;
  const STORAGE_PREFIX = "gemini_chat_history_v1";
  let typingIndicatorNode = null;
  let storageKey = "";

  function setStatus(message, isError = false) {
    statusNode.textContent = message;
    statusNode.classList.toggle("error", isError);
  }

  function scrollToBottom() {
    chatLog.scrollTop = chatLog.scrollHeight;
  }

  function escapeHtml(value) {
    return value
      .replaceAll("&", "&amp;")
      .replaceAll("<", "&lt;")
      .replaceAll(">", "&gt;")
      .replaceAll('"', "&quot;")
      .replaceAll("'", "&#39;");
  }

  function renderInlineMarkdown(value) {
    let html = escapeHtml(value);
    const codeTokens = [];
    const tokenPrefix = "\uE000";
    const tokenSuffix = "\uE001";

    function stashCode(codeText) {
      const token = `${tokenPrefix}${codeTokens.length}${tokenSuffix}`;
      codeTokens.push(`<code>${codeText}</code>`);
      return token;
    }

    html = html.replace(/`([^`\n]+)`/g, (_, codeText) => stashCode(codeText));
    html = html.replace(/%%([^%\n]+)%%/g, (_, codeText) => stashCode(codeText.trim()));

    html = html.replace(/\*\*(.+?)\*\*/gs, "<strong>$1</strong>");
    html = html.replace(/__(.+?)__/gs, "<strong>$1</strong>");
    html = html.replace(/(^|[^*])\*([^*\n]+)\*/g, "$1<em>$2</em>");
    html = html.replace(/(^|[^_])_([^_\n]+)_/g, "$1<em>$2</em>");
    html = html.replace(
      /\[([^\]\n]+)\]\((https?:\/\/[^\s)]+)\)/g,
      '<a href="$2" target="_blank" rel="noopener noreferrer">$1</a>'
    );

    html = html.replace(/\uE000(\d+)\uE001/g, (fullMatch, indexText) => {
      const idx = Number.parseInt(indexText, 10);
      if (!Number.isInteger(idx) || idx < 0 || idx >= codeTokens.length) {
        return fullMatch;
      }
      return codeTokens[idx];
    });

    return html;
  }

  function renderMessageContent(value) {
    const lines = value.replace(/\r\n/g, "\n").split("\n");
    let html = "";
    let inUnorderedList = false;
    let inOrderedList = false;
    let inBlockquote = false;
    let inCodeFence = false;
    let codeFenceLanguage = "";
    const codeFenceLines = [];

    function appendToLastListItem(text) {
      if (!/<\/li>\s*$/.test(html)) return false;
      html = html.replace(/<\/li>\s*$/, `<br>${renderInlineMarkdown(text)}</li>`);
      return true;
    }

    function closeLists() {
      if (inUnorderedList) {
        html += "</ul>";
        inUnorderedList = false;
      }
      if (inOrderedList) {
        html += "</ol>";
        inOrderedList = false;
      }
    }

    function closeBlockquote() {
      if (inBlockquote) {
        html += "</blockquote>";
        inBlockquote = false;
      }
    }

    function closeCodeFence() {
      const langClass = codeFenceLanguage ? ` language-${codeFenceLanguage}` : "";
      html += `<pre class="chat-code-block"><code class="chat-code${langClass}">${escapeHtml(
        codeFenceLines.join("\n")
      )}</code></pre>`;
      inCodeFence = false;
      codeFenceLanguage = "";
      codeFenceLines.length = 0;
    }

    for (const line of lines) {
      if (inCodeFence) {
        if (/^\s*```/.test(line)) {
          closeCodeFence();
        } else {
          codeFenceLines.push(line);
        }
        continue;
      }

      const codeFenceStartMatch = line.match(/^\s*```([\w-]+)?\s*$/);
      if (codeFenceStartMatch) {
        closeLists();
        closeBlockquote();
        inCodeFence = true;
        codeFenceLanguage = (codeFenceStartMatch[1] || "").toLowerCase().replace(/[^a-z0-9_-]/g, "");
        continue;
      }

      if (/^\s{0,3}([-*_])(?:\s*\1){2,}\s*$/.test(line)) {
        closeLists();
        closeBlockquote();
        html += "<hr>";
        continue;
      }

      const headingMatch = line.match(/^\s{0,3}(#{1,6})\s+(.+)$/);
      if (headingMatch) {
        closeLists();
        closeBlockquote();
        const level = headingMatch[1].length;
        html += `<h${level}>${renderInlineMarkdown(headingMatch[2].trim())}</h${level}>`;
        continue;
      }

      const blockquoteMatch = line.match(/^\s*>\s?(.*)$/);
      if (blockquoteMatch) {
        closeLists();
        if (!inBlockquote) {
          html += "<blockquote>";
          inBlockquote = true;
        }

        const quoteText = blockquoteMatch[1].trim();
        if (quoteText === "") {
          html += "<br>";
        } else {
          html += `<p>${renderInlineMarkdown(quoteText)}</p>`;
        }
        continue;
      }

      closeBlockquote();

      const bulletMatch = line.match(/^\s*[*-]\s+(.+)$/);
      const orderedListMatch = line.match(/^\s*\d+[.)]\s*(.+)$/);

      if (bulletMatch) {
        if (inOrderedList) {
          html += "</ol>";
          inOrderedList = false;
        }
        if (!inUnorderedList) {
          html += "<ul>";
          inUnorderedList = true;
        }
        html += `<li>${renderInlineMarkdown(bulletMatch[1])}</li>`;
        continue;
      }

      if (orderedListMatch) {
        if (inUnorderedList) {
          html += "</ul>";
          inUnorderedList = false;
        }
        if (!inOrderedList) {
          html += "<ol>";
          inOrderedList = true;
        }
        html += `<li>${renderInlineMarkdown(orderedListMatch[1])}</li>`;
        continue;
      }

      if (line.trim() === "") {
        if (inUnorderedList || inOrderedList) {
          // Keep ordered list numbering continuous across blank lines.
          continue;
        }
        html += "<br>";
      } else {
        if ((inUnorderedList || inOrderedList) && appendToLastListItem(line.trim())) {
          continue;
        }
        closeLists();
        html += `<p>${renderInlineMarkdown(line)}</p>`;
      }
    }

    if (inCodeFence) {
      closeCodeFence();
    }
    closeLists();
    closeBlockquote();

    return html.replace(/(<br>)+$/g, "");
  }

  function appendMessage(role, content) {
    const article = document.createElement("article");
    article.className = `chat-message ${role}`;

    const text = document.createElement("div");
    text.className = "chat-message-content";
    text.innerHTML = renderMessageContent(content);

    article.appendChild(text);
    chatLog.appendChild(article);
    scrollToBottom();
  }

  function setWelcomeMessage() {
    chatLog.innerHTML = "";
    appendMessage("assistant", "Hello. Enter a prompt below to start chatting.");
  }

  function normalizeStoredConversation(raw) {
    if (!Array.isArray(raw)) return [];

    const normalized = [];
    for (const item of raw) {
      if (!item || typeof item !== "object") continue;

      const role = item.role === "user" ? "user" : item.role === "assistant" ? "assistant" : "";
      const content = typeof item.content === "string" ? item.content.trim() : "";
      if (!role || !content) continue;

      normalized.push({ role, content });
      if (normalized.length >= MAX_STORED_MESSAGES) {
        break;
      }
    }

    return normalized;
  }

  function loadConversationFromStorage() {
    if (!storageKey) return [];

    try {
      const raw = localStorage.getItem(storageKey);
      if (!raw) return [];
      return normalizeStoredConversation(JSON.parse(raw));
    } catch {
      return [];
    }
  }

  function saveConversationToStorage() {
    if (!storageKey) return;

    const toStore = conversation.slice(-MAX_STORED_MESSAGES);
    try {
      localStorage.setItem(storageKey, JSON.stringify(toStore));
    } catch {
      // Ignore storage errors (quota/private mode).
    }
  }

  async function initConversationFromStorage() {
    try {
      const payload = await apiRequest("me.php", { method: "GET", headers: {} });
      if (!payload?.authenticated || !payload?.user) {
        setWelcomeMessage();
        return;
      }

      const user = payload.user;
      const userKeyPart = user.id ? `id_${user.id}` : user.email ? `email_${String(user.email).toLowerCase()}` : "";
      if (!userKeyPart) {
        setWelcomeMessage();
        return;
      }

      storageKey = `${STORAGE_PREFIX}:${userKeyPart}`;

      const storedConversation = loadConversationFromStorage();
      conversation.length = 0;
      conversation.push(...storedConversation);

      chatLog.innerHTML = "";
      if (storedConversation.length === 0) {
        appendMessage("assistant", "Hello. Enter a prompt below to start chatting.");
      } else {
        for (const entry of storedConversation) {
          appendMessage(entry.role, entry.content);
        }
      }
    } catch {
      setWelcomeMessage();
    }
  }

  function showTypingIndicator() {
    if (typingIndicatorNode) return;

    const article = document.createElement("article");
    article.className = "chat-message assistant typing-indicator";

    const dots = document.createElement("div");
    dots.className = "typing-dots";
    dots.setAttribute("aria-label", "Gemini is typing");

    for (let i = 0; i < 3; i += 1) {
      const dot = document.createElement("span");
      dots.appendChild(dot);
    }

    article.appendChild(dots);
    chatLog.appendChild(article);
    typingIndicatorNode = article;
    scrollToBottom();
  }

  function hideTypingIndicator() {
    if (!typingIndicatorNode) return;
    typingIndicatorNode.remove();
    typingIndicatorNode = null;
  }

  function resetChat() {
    conversation.length = 0;
    hideTypingIndicator();
    setWelcomeMessage();
    saveConversationToStorage();
    setStatus("");
    promptInput.focus();
  }

  async function sendPrompt(prompt, history) {
    const payload = await apiRequest("gemini_chat.php", {
      method: "POST",
      body: JSON.stringify({
        prompt,
        history,
      }),
    });

    return (payload.reply || "").trim();
  }

  chatForm.addEventListener("submit", async (event) => {
    event.preventDefault();

    const prompt = promptInput.value.trim();
    if (!prompt) {
      setStatus("Type a message first.", true);
      return;
    }

    const history = conversation.slice(-MAX_HISTORY_MESSAGES);
    conversation.push({ role: "user", content: prompt });
    saveConversationToStorage();
    appendMessage("user", prompt);
    promptInput.value = "";
    setStatus("Waiting for Gemini...");

    const submitBtn = chatForm.querySelector('button[type="submit"]');
    if (submitBtn) submitBtn.disabled = true;
    promptInput.disabled = true;
    clearBtn.disabled = true;
    showTypingIndicator();

    try {
      const reply = await sendPrompt(prompt, history);
      hideTypingIndicator();

      if (!reply) {
        throw new Error("Gemini returned an empty response.");
      }

      conversation.push({ role: "assistant", content: reply });
      saveConversationToStorage();
      appendMessage("assistant", reply);
      setStatus("Response received.");
    } catch (error) {
      hideTypingIndicator();
      const message = error instanceof Error ? error.message : "Request failed.";
      setStatus(message, true);
      appendMessage("assistant", "I could not fetch a response right now. Please try again.");
    } finally {
      if (submitBtn) submitBtn.disabled = false;
      promptInput.disabled = false;
      clearBtn.disabled = false;
      promptInput.focus();
    }
  });

  clearBtn.addEventListener("click", resetChat);

  promptInput.addEventListener("keydown", (event) => {
    if (event.key !== "Enter" || event.shiftKey || event.isComposing) {
      return;
    }

    event.preventDefault();
    if (!promptInput.disabled) {
      chatForm.requestSubmit();
    }
  });

  initConversationFromStorage();
})();
