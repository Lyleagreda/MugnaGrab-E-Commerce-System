// JavaScript for handling product ratings

document.addEventListener("DOMContentLoaded", () => {
  // Rating modal elements
  const ratingModal = document.getElementById("rating-modal")
  const ratingModalClose = document.querySelector(".rating-modal-close")
  const ratingProductsContainer = document.getElementById("rating-products-container")
  const submitRatingsBtn = document.getElementById("submit-ratings")
  let currentOrderId = null

  // Get the base URL for API requests (relative to the current page)
  const getBaseUrl = () => {
    // This will return the current path without the filename
    // e.g., if we're on /subfolder/account.php, it returns /subfolder/
    const pathParts = window.location.pathname.split("/")
    pathParts.pop() // Remove the filename
    return pathParts.join("/") + "/"
  }

  // Close rating modal when clicking close button
  if (ratingModalClose) {
    ratingModalClose.addEventListener("click", () => {
      ratingModal.style.display = "none"
      document.body.style.overflow = "auto"
    })
  }

  // Close rating modal when clicking outside the modal
  window.addEventListener("click", (event) => {
    if (event.target === ratingModal) {
      ratingModal.style.display = "none"
      document.body.style.overflow = "auto"
    }
  })

  // Add event listeners to Rate Products buttons
  document.querySelectorAll(".rate-products-btn").forEach((button) => {
    button.addEventListener("click", function () {
      openRatingModal(this.dataset.orderId)
    })
  })

  // Function to open the rating modal
  function openRatingModal(orderId) {
    console.log("Opening rating modal for order ID:", orderId)

    // First check if the order has already been rated
    fetch(`account.php?action=check_order_rated&order_id=${orderId}`)
      .then((response) => {
        if (!response.ok) {
          throw new Error("Network response was not ok")
        }
        return response.json()
      })
      .then((data) => {
        console.log("Check order rated response:", data)

        if (data.success) {
          if (data.isRated) {
            showToast("error", "You have already rated this order")
            return
          }

          // Fetch order items for rating
          fetch(`account.php?action=get_order_items&order_id=${orderId}`)
            .then((response) => {
              if (!response.ok) {
                throw new Error("Network response was not ok")
              }
              return response.json()
            })
            .then((data) => {
              console.log("Get order items response:", data)

              if (data.success && data.items && data.items.length > 0) {
                currentOrderId = orderId
                showRatingModal(data.items)
              } else {
                showToast("error", data.message || "No items found to rate")
              }
            })
            .catch((error) => {
              console.error("Error fetching order items:", error)
              showToast("error", "An error occurred while fetching order items. Please try again.")
            })
        } else {
          showToast("error", data.message || "Could not check rating status")
        }
      })
      .catch((error) => {
        console.error("Error checking if order is rated:", error)
        showToast("error", "An error occurred. Please try again.")
      })
  }

  // Function to show the rating modal with products
  function showRatingModal(products) {
    // Clear previous products
    ratingProductsContainer.innerHTML = ""

    // Add each product to the modal
    products.forEach((product) => {
      const productDiv = document.createElement("div")
      productDiv.className = "rating-product"
      productDiv.dataset.productId = product.product_id

      // Find product image from the products array
      let productImage = "images/products/default.jpg"
      for (const p of Object.values(window.productsData || {})) {
        if (p.id == product.product_id && p.image) {
          productImage = p.image
          break
        }
      }

      productDiv.innerHTML = `
                <div class="rating-product-image">
                    <img src="${productImage}" alt="${product.product_name}">
                </div>
                <div class="rating-product-details">
                    <h4 class="rating-product-name">${product.product_name}</h4>
                    <div class="rating-stars" data-product-id="${product.product_id}">
                        <input type="radio" id="star5-${product.product_id}" name="rating-${product.product_id}" value="5" />
                        <label for="star5-${product.product_id}" title="5 stars"><i class="fas fa-star"></i></label>
                        
                        <input type="radio" id="star4-${product.product_id}" name="rating-${product.product_id}" value="4" />
                        <label for="star4-${product.product_id}" title="4 stars"><i class="fas fa-star"></i></label>
                        
                        <input type="radio" id="star3-${product.product_id}" name="rating-${product.product_id}" value="3" />
                        <label for="star3-${product.product_id}" title="3 stars"><i class="fas fa-star"></i></label>
                        
                        <input type="radio" id="star2-${product.product_id}" name="rating-${product.product_id}" value="2" />
                        <label for="star2-${product.product_id}" title="2 stars"><i class="fas fa-star"></i></label>
                        
                        <input type="radio" id="star1-${product.product_id}" name="rating-${product.product_id}" value="1" />
                        <label for="star1-${product.product_id}" title="1 star"><i class="fas fa-star"></i></label>
                    </div>
                </div>
            `

      ratingProductsContainer.appendChild(productDiv)
    })

    // Reset submit button
    submitRatingsBtn.disabled = false
    submitRatingsBtn.textContent = "Submit Ratings"

    // Show the modal
    ratingModal.style.display = "block"
    document.body.style.overflow = "hidden"

    // Add direct click handlers to the star labels
    document.querySelectorAll(".rating-stars label").forEach((label) => {
      label.addEventListener("click", function () {
        const forAttr = this.getAttribute("for")
        if (forAttr) {
          const input = document.getElementById(forAttr)
          if (input) {
            input.checked = true
            console.log(`Selected rating: ${input.value} for product ${input.name.split("-")[1]}`)
          }
        }
      })
    })
  }

  // Handle submit ratings button with direct DOM method
  if (submitRatingsBtn) {
    console.log("Found submit button:", submitRatingsBtn)

    // Remove any existing event listeners
    const newSubmitBtn = submitRatingsBtn.cloneNode(true)
    submitRatingsBtn.parentNode.replaceChild(newSubmitBtn, submitRatingsBtn)

    // Add new event listener
    newSubmitBtn.addEventListener("click", submitRatings)

    // Also add onclick property as a fallback
    newSubmitBtn.onclick = submitRatings
  }

  // Function to handle rating submission
  function submitRatings(e) {
    console.log("Submit button clicked", e)

    // Prevent default if it's an event
    if (e && e.preventDefault) {
      e.preventDefault()
    }

    // Collect all ratings
    const ratings = []
    const ratingStars = document.querySelectorAll(".rating-stars")

    console.log("Found rating stars:", ratingStars.length)

    let allRated = true

    ratingStars.forEach((stars) => {
      const productId = stars.dataset.productId
      const selectedRating = stars.querySelector("input:checked")

      console.log(`Product ${productId} rating:`, selectedRating ? selectedRating.value : "none")

      if (selectedRating) {
        ratings.push({
          product_id: productId,
          rating: Number.parseInt(selectedRating.value),
        })
      } else {
        allRated = false
        // Highlight unrated products
        stars.closest(".rating-product").style.border = "2px solid red"
      }
    })

    // Check if all products have been rated
    if (!allRated) {
      showToast("error", "Please rate all products before submitting")
      return
    }

    // Get the submit button again in case it was replaced
    const submitBtn = document.getElementById("submit-ratings")

    // Disable submit button
    if (submitBtn) {
      submitBtn.disabled = true
      submitBtn.textContent = "Submitting..."
    }

    console.log("Submitting ratings:", { ratings, order_id: currentOrderId })

    // Send ratings to server
    fetch("rate-products.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify({
        ratings: ratings,
        order_id: currentOrderId,
      }),
    })
      .then((response) => {
        console.log("Response status:", response.status)
        if (!response.ok) {
          throw new Error("Network response was not ok")
        }
        return response.json()
      })
      .then((data) => {
        console.log("Rating submission response:", data)

        if (data.success) {
          // Show success message
          ratingModal.querySelector(".rating-modal-body").innerHTML = `
                    <div class="rating-success">
                        <div class="rating-success-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <h3 class="rating-success-title">Thank You!</h3>
                        <p class="rating-success-message">Your ratings have been submitted successfully.</p>
                        <button type="button" class="btn btn-primary" onclick="closeRatingModal()">Continue Shopping</button>
                    </div>
                `

          // Hide footer
          ratingModal.querySelector(".rating-modal-footer").style.display = "none"

          showToast("success", "Ratings submitted successfully")

          // Disable the rate products button for this order
          const rateButton = document.querySelector(`.rate-products-btn[data-order-id="${currentOrderId}"]`)
          if (rateButton) {
            rateButton.disabled = true
            rateButton.classList.add("disabled")
            rateButton.title = "You have already rated this order"
          }
        } else {
          showToast("error", data.message || "Failed to submit ratings")
          if (submitBtn) {
            submitBtn.disabled = false
            submitBtn.textContent = "Submit Ratings"
          }
        }
      })
      .catch((error) => {
        console.error("Error submitting ratings:", error)
        showToast("error", "An error occurred. Please try again.")
        if (submitBtn) {
          submitBtn.disabled = false
          submitBtn.textContent = "Submit Ratings"
        }
      })
  }

  // Function to show toast notifications
  const showToast = (type, message) => {
    const toastContainer = document.getElementById("toast-container")
    if (!toastContainer) {
      // Create toast container if it doesn't exist
      const newToastContainer = document.createElement("div")
      newToastContainer.id = "toast-container"
      newToastContainer.className = "toast-container"
      document.body.appendChild(newToastContainer)
      const toastContainer = newToastContainer
    }

    const toast = document.createElement("div")
    toast.className = `toast toast-${type}`

    const iconDiv = document.createElement("div")
    iconDiv.className = "toast-icon"
    let icon = ""
    if (type === "success") {
      icon = '<i class="fas fa-check-circle"></i>'
    } else if (type === "error") {
      icon = '<i class="fas fa-times-circle"></i>'
    }
    iconDiv.innerHTML = icon

    const contentDiv = document.createElement("div")
    contentDiv.className = "toast-content"
    contentDiv.innerHTML = `
            <h6 class="toast-title">${type === "success" ? "Success" : "Error"}</h6>
            <p class="toast-message">${message}</p>
        `

    const closeButton = document.createElement("button")
    closeButton.className = "toast-close"
    closeButton.innerHTML = "&times;"
    closeButton.addEventListener("click", () => {
      toast.classList.add("hide")
      toast.addEventListener(
        "animationend",
        () => {
          toast.remove()
        },
        { once: true },
      )
    })

    toast.appendChild(iconDiv)
    toast.appendChild(contentDiv)
    toast.appendChild(closeButton)
    toastContainer.appendChild(toast)

    // Automatically remove the toast after a delay
    setTimeout(() => {
      toast.classList.add("hide")
      toast.addEventListener(
        "animationend",
        () => {
          toast.remove()
        },
        { once: true },
      )
    }, 5000)
  }

  // Make closeRatingModal available globally
  window.closeRatingModal = () => {
    if (ratingModal) {
      ratingModal.style.display = "none"
      document.body.style.overflow = "auto"
    }
  }
})
