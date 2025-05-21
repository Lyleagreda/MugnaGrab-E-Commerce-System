document.addEventListener("DOMContentLoaded", () => {
    const signupForm = document.getElementById("signupForm")
    const signupButton = document.getElementById("signupButton")
    const phoneInput = document.getElementById("phone")
    const passwordInput = document.getElementById("password")
    const confirmPasswordInput = document.getElementById("password_confirm")
  
    // Phone number formatting
    if (phoneInput) {
      phoneInput.addEventListener("input", (e) => {
        let value = e.target.value.replace(/\D/g, "")
  
        // Format as 09XX XXX XXXX
        if (value.startsWith("0")) {
          if (value.length > 4) {
            value = value.substring(0, 4) + " " + value.substring(4)
          }
          if (value.length > 8) {
            value = value.substring(0, 8) + " " + value.substring(8)
          }
          if (value.length > 13) {
            value = value.substring(0, 13)
          }
        }
        // Format as +63 9XX XXX XXXX
        else if (value.startsWith("63")) {
          value = "+" + value.substring(0, 2) + " " + value.substring(2)
          if (value.length > 7) {
            value = value.substring(0, 7) + " " + value.substring(7)
          }
          if (value.length > 11) {
            value = value.substring(0, 11) + " " + value.substring(11)
          }
          if (value.length > 16) {
            value = value.substring(0, 16)
          }
        }
        // If user enters just 9 as first digit, assume +63 9
        else if (value.startsWith("9")) {
          value = "+63 " + value
          if (value.length > 7) {
            value = value.substring(0, 7) + " " + value.substring(7)
          }
          if (value.length > 11) {
            value = value.substring(0, 11) + " " + value.substring(11)
          }
          if (value.length > 16) {
            value = value.substring(0, 16)
          }
        }
  
        e.target.value = value
      })
    }
  
    // Password validation
    if (passwordInput) {
      passwordInput.addEventListener("input", () => {
        validatePassword()
      })
    }
  
    if (confirmPasswordInput) {
      confirmPasswordInput.addEventListener("input", () => {
        validatePasswordMatch()
      })
    }
  
    function validatePassword() {
      const password = passwordInput.value
      const hasLetters = /[A-Za-z]/.test(password)
      const hasNumbers = /[0-9]/.test(password)
      const isLongEnough = password.length >= 8
  
      if (!isLongEnough || !hasLetters || !hasNumbers) {
        passwordInput.classList.add("invalid")
      } else {
        passwordInput.classList.remove("invalid")
      }
    }
  
    function validatePasswordMatch() {
      if (passwordInput.value !== confirmPasswordInput.value) {
        confirmPasswordInput.classList.add("invalid")
      } else {
        confirmPasswordInput.classList.remove("invalid")
      }
    }
  
    // Form submission
    if (signupForm) {
      signupForm.addEventListener("submit", (e) => {
        e.preventDefault()
  
        // Basic client-side validation
        const password = passwordInput.value
        const passwordConfirm = confirmPasswordInput.value
        const terms = document.getElementById("terms").checked
  
        let isValid = true
        let errorMessage = ""
  
        if (password.length < 8) {
          isValid = false
          errorMessage += "Password must be at least 8 characters long.\n"
        }
  
        if (!/[A-Za-z]/.test(password) || !/[0-9]/.test(password)) {
          isValid = false
          errorMessage += "Password must contain both letters and numbers.\n"
        }
  
        if (password !== passwordConfirm) {
          isValid = false
          errorMessage += "Passwords do not match.\n"
        }
  
        if (!terms) {
          isValid = false
          errorMessage += "You must agree to the Terms of Service and Privacy Policy.\n"
        }
  
        if (!isValid) {
          alert(errorMessage)
          return
        }
  
        // Show loading state
        signupButton.textContent = "Creating account..."
        signupButton.disabled = true
  
        // Submit the form after a short delay to show the loading state
        setTimeout(() => {
          signupForm.submit()
        }, 1000)
      })
    }
  })
  
  