import { Chart } from "@/components/ui/chart"
document.addEventListener("DOMContentLoaded", () => {
  // Sales Chart
  const salesChartEl = document.getElementById("salesChart")
  if (salesChartEl) {
    const salesChart = new Chart(salesChartEl, {
      type: "line",
      data: {
        labels: ["Mon", "Tue", "Wed", "Thu", "Fri", "Sat", "Sun"],
        datasets: [
          {
            label: "This Week",
            data: [65000, 59000, 80000, 81000, 56000, 85000, 90000],
            borderColor: "#2563eb",
            backgroundColor: "rgba(37, 99, 235, 0.1)",
            tension: 0.4,
            fill: true,
          },
          {
            label: "Last Week",
            data: [45000, 55000, 75000, 70000, 50000, 80000, 75000],
            borderColor: "#94a3b8",
            backgroundColor: "rgba(148, 163, 184, 0.1)",
            tension: 0.4,
            fill: true,
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            position: "top",
          },
          tooltip: {
            callbacks: {
              label: (context) => {
                let label = context.dataset.label || ""
                if (label) {
                  label += ": "
                }
                if (context.parsed.y !== null) {
                  label += new Intl.NumberFormat("en-PH", {
                    style: "currency",
                    currency: "PHP",
                    minimumFractionDigits: 0,
                    maximumFractionDigits: 0,
                  }).format(context.parsed.y)
                }
                return label
              },
            },
          },
        },
        scales: {
          y: {
            beginAtZero: true,
            ticks: {
              callback: (value) => "₱" + value / 1000 + "k",
            },
          },
        },
      },
    })
  }

  // Category Chart
  const categoryChartEl = document.getElementById("categoryChart")
  if (categoryChartEl) {
    const categoryChart = new Chart(categoryChartEl, {
      type: "doughnut",
      data: {
        labels: ["Mens Bag & Accessories", "Women Accessories", "Womens Bags", "Sports & Travel", "Hobbies & Stationery", "Mobile Accessories", "Laptops & Computers"],
        datasets: [
          {
            data: [37, 21, 9, 4, 3, 1, 1],
            backgroundColor: ["#2563eb", "#10b981", "#f59e0b", "#ef4444", "#8b5cf6"],
            borderWidth: 0,
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            position: "right",
          },
          tooltip: {
            callbacks: {
              label: (context) => {
                const label = context.label || ""
                const value = context.parsed || 0
                const total = context.dataset.data.reduce((a, b) => a + b, 0)
                const percentage = Math.round((value / total) * 100)
                return `${label}: ${percentage}% (₱${value}k)`
              },
            },
          },
        },
        cutout: "70%",
      },
    })
  }
})
