document.addEventListener("DOMContentLoaded", () => {
  // Mobile menu toggle
  const menuBtn = document.querySelector(".menu-btn")
  const mobileMenu = document.querySelector(".mobile-menu")

  if (menuBtn && mobileMenu) {
    menuBtn.addEventListener("click", () => {
      mobileMenu.classList.toggle("active")
      const icon = menuBtn.querySelector("i")
      if (mobileMenu.classList.contains("active")) {
        icon.classList.remove("fa-bars")
        icon.classList.add("fa-times")
      } else {
        icon.classList.remove("fa-times")
        icon.classList.add("fa-bars")
      }
    })
  }
  

  // Slideshow functionality
  const slideshow = document.getElementById("productSlideshow")
  if (slideshow) {
    const slides = slideshow.querySelectorAll(".slide")
    const indicators = slideshow.querySelectorAll(".indicator")
    const prevBtn = slideshow.querySelector(".prev-arrow")
    const nextBtn = slideshow.querySelector(".next-arrow")
    let currentSlide = 0
    let slideInterval

    // Function to show a specific slide
    function showSlide(index) {
      // Hide all slides
      slides.forEach((slide) => {
        slide.classList.remove("active")
      })

      // Deactivate all indicators
      indicators.forEach((indicator) => {
        indicator.classList.remove("active")
      })

      // Show the selected slide
      slides[index].classList.add("active")
      indicators[index].classList.add("active")

      // Update current slide index
      currentSlide = index
    }

    // Next slide function
    function nextSlide() {
      let next = currentSlide + 1
      if (next >= slides.length) {
        next = 0
      }
      showSlide(next)
    }

    // Previous slide function
    function prevSlide() {
      let prev = currentSlide - 1
      if (prev < 0) {
        prev = slides.length - 1
      }
      showSlide(prev)
    }

    // Set up event listeners
    if (nextBtn) {
      nextBtn.addEventListener("click", () => {
        nextSlide()
        resetInterval()
      })
    }

    if (prevBtn) {
      prevBtn.addEventListener("click", () => {
        prevSlide()
        resetInterval()
      })
    }

    // Set up indicator clicks
    indicators.forEach((indicator, index) => {
      indicator.addEventListener("click", () => {
        showSlide(index)
        resetInterval()
      })
    })

    // Auto-advance slides
    function startInterval() {
      slideInterval = setInterval(nextSlide, 5000)
    }

    function resetInterval() {
      clearInterval(slideInterval)
      startInterval()
    }

    // Start the slideshow
    startInterval()
  }

  // Wishlist functionality
  const wishlistBtns = document.querySelectorAll(".wishlist-btn")
  wishlistBtns.forEach((btn) => {
    btn.addEventListener("click", function () {
      const icon = this.querySelector("i")
      if (icon.classList.contains("far")) {
        icon.classList.remove("far")
        icon.classList.add("fas")
        this.classList.add("active")
      } else {
        icon.classList.remove("fas")
        icon.classList.add("far")
        this.classList.remove("active")
      }
    })
  })

  // Add to cart functionality
  const addToCartBtns = document.querySelectorAll(".add-to-cart")
  addToCartBtns.forEach((btn) => {
    btn.addEventListener("click", function () {
      const productId = this.getAttribute("data-product-id")
      // In a real app, you would add the product to the cart
      // For this demo, we'll just show an alert
      alert(`Product ${productId} added to cart!`)

      // Update cart count
      const cartCount = document.querySelector(".cart-count")
      if (cartCount) {
        const count = Number.parseInt(cartCount.textContent)
        cartCount.textContent = count + 1
      }
    })
  })
})

