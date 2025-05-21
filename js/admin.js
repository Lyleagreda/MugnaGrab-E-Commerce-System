document.addEventListener("DOMContentLoaded", () => {
  // Sidebar toggle
  const sidebarToggle = document.getElementById("sidebarToggle")
  const menuToggle = document.getElementById("menuToggle")
  const adminContainer = document.querySelector(".admin-container")
  const sidebar = document.querySelector(".sidebar")

  if (sidebarToggle) {
    sidebarToggle.addEventListener("click", () => {
      sidebar.classList.toggle("collapsed")
      adminContainer.classList.toggle("collapsed")
    })
  }

  if (menuToggle) {
    menuToggle.addEventListener("click", () => {
      sidebar.classList.toggle("active")
    })
  }

  // Close sidebar when clicking outside on mobile
  document.addEventListener("click", (event) => {
    if (window.innerWidth < 992) {
      if (
        !sidebar.contains(event.target) &&
        !menuToggle.contains(event.target) &&
        sidebar.classList.contains("active")
      ) {
        sidebar.classList.remove("active")
      }
    }
  })

  // Select all checkbox
  const selectAll = document.getElementById("select-all")
  if (selectAll) {
    selectAll.addEventListener("change", () => {
      const checkboxes = document.querySelectorAll(".product-select")
      checkboxes.forEach((checkbox) => {
        checkbox.checked = selectAll.checked
      })
    })
  }

  // Slideshow preview
  const previewDots = document.querySelectorAll(".preview-dots .dot")
  if (previewDots.length > 0) {
    previewDots.forEach((dot) => {
      dot.addEventListener("click", function () {
        previewDots.forEach((d) => d.classList.remove("active"))
        this.classList.add("active")
      })
    })
  }

  // Slideshow arrows
  const prevArrow = document.querySelector(".preview-arrow.prev")
  const nextArrow = document.querySelector(".preview-arrow.next")
  if (prevArrow && nextArrow) {
    prevArrow.addEventListener("click", () => {
      const activeDot = document.querySelector(".preview-dots .dot.active")
      const prevDot = activeDot.previousElementSibling || document.querySelector(".preview-dots .dot:last-child")
      activeDot.classList.remove("active")
      prevDot.classList.add("active")
    })

    nextArrow.addEventListener("click", () => {
      const activeDot = document.querySelector(".preview-dots .dot.active")
      const nextDot = activeDot.nextElementSibling || document.querySelector(".preview-dots .dot:first-child")
      activeDot.classList.remove("active")
      nextDot.classList.add("active")
    })
  }
})
