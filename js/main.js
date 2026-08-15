// Мобильное меню, состояние шапки и плавное появление секций.
const header = document.querySelector("[data-header]");
const burger = document.querySelector("[data-burger]");
const nav = document.querySelector("[data-nav]");
const themeToggle = document.querySelector("[data-theme-toggle]");
const themeIcon = document.querySelector("[data-theme-icon]");
const form = document.querySelector("[data-form]");
const formMessage = document.querySelector("[data-form-message]");

// Переключатель темы хранит выбор пользователя прямо в браузере.
function applyTheme(theme) {
  document.documentElement.dataset.theme = theme;
  const isDark = theme === "dark";
  themeToggle.setAttribute("aria-pressed", String(isDark));
  themeToggle.setAttribute("aria-label", isDark ? "Включить светлую тему" : "Включить темную тему");
  themeIcon.textContent = isDark ? "☾" : "☀";
}

let savedTheme = "light";

try {
  savedTheme = localStorage.getItem("rikLabTheme") || "light";
} catch (error) {
  savedTheme = "light";
}

applyTheme(savedTheme);

function updateHeader() {
  header.classList.toggle("is-scrolled", window.scrollY > 12);
}

window.addEventListener("scroll", updateHeader, { passive: true });
updateHeader();

burger.addEventListener("click", () => {
  const isOpen = burger.classList.toggle("is-open");
  nav.classList.toggle("is-open", isOpen);
  burger.setAttribute("aria-expanded", String(isOpen));
  burger.setAttribute("aria-label", isOpen ? "Закрыть меню" : "Открыть меню");
});

nav.addEventListener("click", (event) => {
  if (event.target.tagName === "A") {
    burger.classList.remove("is-open");
    nav.classList.remove("is-open");
    burger.setAttribute("aria-expanded", "false");
    burger.setAttribute("aria-label", "Открыть меню");
  }
});

themeToggle.addEventListener("click", () => {
  const nextTheme = document.documentElement.dataset.theme === "dark" ? "light" : "dark";
  try {
    localStorage.setItem("rikLabTheme", nextTheme);
  } catch (error) {
    // Если браузер открыл локальный файл с ограничениями, тема все равно переключится.
  }
  applyTheme(nextTheme);
});

// IntersectionObserver дает аккуратное появление блоков без тяжелых библиотек.
const revealObserver = new IntersectionObserver((entries) => {
  entries.forEach((entry) => {
    if (entry.isIntersecting) {
      entry.target.classList.add("is-visible");
      revealObserver.unobserve(entry.target);
    }
  });
}, {
  threshold: 0.12
});

document.querySelectorAll(".section-reveal").forEach((section) => {
  revealObserver.observe(section);
});

// Отправка заявки через PHP-обработчик на сервере.
form.addEventListener("submit", async (event) => {
  event.preventDefault();

  const submitButton = form.querySelector("button[type='submit']");
  const formData = new FormData(form);
  const defaultButtonText = submitButton.textContent;

  formMessage.textContent = "Отправляем заявку...";
  submitButton.disabled = true;
  submitButton.textContent = "Отправка...";

  try {
    const response = await fetch(form.action, {
      method: "POST",
      body: formData,
      headers: {
        "Accept": "application/json"
      }
    });

    const result = await response.json();

    if (!response.ok || !result.success) {
      throw new Error(result.message || "Не удалось отправить заявку.");
    }

    formMessage.textContent = result.message || "Спасибо, заявка отправлена.";
    form.reset();
    if (window.smartCaptcha && typeof window.smartCaptcha.reset === "function") {
      window.smartCaptcha.reset();
    }
  } catch (error) {
    formMessage.textContent = error.message || "Не удалось отправить заявку. Позвоните нам: +7 995 918-65-16.";
    if (window.smartCaptcha && typeof window.smartCaptcha.reset === "function") {
      window.smartCaptcha.reset();
    }
  } finally {
    submitButton.disabled = false;
    submitButton.textContent = defaultButtonText;
  }
});
