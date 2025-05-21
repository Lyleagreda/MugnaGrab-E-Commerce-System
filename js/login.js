document.addEventListener("DOMContentLoaded", () => {
  const loginForm = document.getElementById("loginForm")
  const loginButton = document.getElementById("loginButton")

  if (loginForm) {
    loginForm.addEventListener("submit", (e) => {
      e.preventDefault()

      // Show loading state
      loginButton.classList.add("loading")

      // Simulate form submission delay
      setTimeout(() => {
        loginForm.submit()
      }, 1500)
    })
  }
})

