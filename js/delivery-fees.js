document.addEventListener("DOMContentLoaded", () => {
  // Form validation for add fee form
  const addFeeForm = document.getElementById("addFeeForm")
  if (addFeeForm) {
    addFeeForm.addEventListener("submit", (event) => {
      const city = document.getElementById("city").value.trim()
      const state = document.getElementById("state").value.trim()
      const feeAmount = document.getElementById("fee_amount").value.trim()

      if (!city || !state || !feeAmount) {
        event.preventDefault()
        alert("Please fill in all required fields.")
        return false
      }

      if (Number.parseFloat(feeAmount) < 0) {
        event.preventDefault()
        alert("Fee amount cannot be negative.")
        return false
      }

      const minOrderFreeDelivery = document.getElementById("min_order_free_delivery").value.trim()
      if (minOrderFreeDelivery && Number.parseFloat(minOrderFreeDelivery) < 0) {
        event.preventDefault()
        alert("Minimum order for free delivery cannot be negative.")
        return false
      }
    })
  }

  // Form validation for edit fee form
  const editFeeForm = document.getElementById("editFeeForm")
  if (editFeeForm) {
    editFeeForm.addEventListener("submit", (event) => {
      const city = document.getElementById("edit_city").value.trim()
      const state = document.getElementById("edit_state").value.trim()
      const feeAmount = document.getElementById("edit_fee_amount").value.trim()

      if (!city || !state || !feeAmount) {
        event.preventDefault()
        alert("Please fill in all required fields.")
        return false
      }

      if (Number.parseFloat(feeAmount) < 0) {
        event.preventDefault()
        alert("Fee amount cannot be negative.")
        return false
      }

      const minOrderFreeDelivery = document.getElementById("edit_min_order_free_delivery").value.trim()
      if (minOrderFreeDelivery && Number.parseFloat(minOrderFreeDelivery) < 0) {
        event.preventDefault()
        alert("Minimum order for free delivery cannot be negative.")
        return false
      }
    })
  }

  // Confirm delete
  const deleteFeeForm = document.getElementById("deleteFeeForm")
  if (deleteFeeForm) {
    deleteFeeForm.addEventListener("submit", (event) => {
      if (!confirm("Are you sure you want to delete this delivery fee? This action cannot be undone.")) {
        event.preventDefault()
        return false
      }
    })
  }

  // Toggle availability confirmation
  const toggleForms = document.querySelectorAll(".toggle-form")
  toggleForms.forEach((form) => {
    form.addEventListener("submit", function (event) {
      const currentStatus = this.querySelector('input[name="current_status"]').value
      const newStatus = currentStatus === "1" ? "unavailable" : "available"

      if (!confirm(`Are you sure you want to make this location ${newStatus} for delivery?`)) {
        event.preventDefault()
        return false
      }
    })
  })

  // Export to CSV functionality
  const exportButton = document.getElementById("exportCSV")
  if (exportButton) {
    exportButton.addEventListener("click", () => {
      const table = document.getElementById("deliveryFeesTable")
      const rows = table.querySelectorAll('tbody tr:not([style*="display: none"])')

      if (rows.length === 0) {
        alert("No data to export.")
        return
      }

      let csvContent = "ID,City,State,Fee Amount,Min Order for Free Delivery,Estimated Days,Status,Last Updated\n"

      rows.forEach((row) => {
        const cells = row.querySelectorAll("td")
        const rowData = [
          cells[0].textContent, // ID
          `"${cells[1].textContent}"`, // City
          `"${cells[2].textContent}"`, // State
          cells[3].textContent.replace("$", ""), // Fee Amount
          cells[4].textContent
            .replace("$", "")
            .replace("N/A", ""), // Min Order
          `"${cells[5].textContent}"`, // Estimated Days
          cells[6].textContent.trim(), // Status
          `"${cells[7].textContent}"`, // Last Updated
        ]
        csvContent += rowData.join(",") + "\n"
      })

      const blob = new Blob([csvContent], { type: "text/csv;charset=utf-8;" })
      const link = document.createElement("a")
      const url = URL.createObjectURL(blob)

      link.setAttribute("href", url)
      link.setAttribute("download", "delivery_fees.csv")
      link.style.visibility = "hidden"

      document.body.appendChild(link)
      link.click()
      document.body.removeChild(link)
    })
  }
})
