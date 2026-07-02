// Мобильное меню, состояние шапки и плавное появление секций.
const header = document.querySelector("[data-header]");
const burger = document.querySelector("[data-burger]");
const nav = document.querySelector("[data-nav]");
const themeToggle = document.querySelector("[data-theme-toggle]");
const themeLabel = document.querySelector("[data-theme-label]");
const form = document.querySelector("[data-form]");
const formMessage = document.querySelector("[data-form-message]");

// Переключатель темы хранит выбор пользователя прямо в браузере.
function applyTheme(theme) {
  document.documentElement.dataset.theme = theme;
  const isDark = theme === "dark";
  themeToggle.setAttribute("aria-pressed", String(isDark));
  themeToggle.setAttribute("aria-label", isDark ? "Включить светлую тему" : "Включить темную тему");
  themeLabel.textContent = isDark ? "Темная" : "Светлая";
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

// Заглушка отправки формы до подключения почты или CRM.
form.addEventListener("submit", (event) => {
  event.preventDefault();
  formMessage.textContent = "Спасибо, заявка подготовлена. Подключим отправку после настройки почты или CRM.";
  form.reset();
});
