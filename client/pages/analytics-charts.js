// Analytics Charts Implementation
document.addEventListener("DOMContentLoaded", function () {
  // Initialize all charts
  initializeCashFlowChart();
  initializeSpendingPieChart();
  initializeCategoryTrendsChart();
  initializeVelocityChart();
  initializeRecipientsChart();

  // Set up period selector
  setupPeriodSelector();

  // Set up refresh button
  setupRefreshButton();

  // Update last updated time
  updateLastUpdatedTime();
});

// Period selector functionality
function setupPeriodSelector() {
  const periodBtns = document.querySelectorAll(".period-btn");
  periodBtns.forEach((btn) => {
    btn.addEventListener("click", function () {
      const period = this.getAttribute("data-period");
      window.location.href = `analytics.php?period=${period}`;
    });
  });
}

// Refresh button functionality
function setupRefreshButton() {
  const refreshBtn = document.getElementById("refresh-data");
  refreshBtn.addEventListener("click", function () {
    this.classList.add("fa-spin");
    setTimeout(() => {
      window.location.reload();
    }, 1000);
  });
}

// Update last updated time
function updateLastUpdatedTime() {
  const now = new Date();
  const timeString = now.toLocaleTimeString("en-US", {
    hour: "2-digit",
    minute: "2-digit",
    second: "2-digit",
  });
  document.getElementById(
    "last-updated"
  ).textContent = `Last updated: ${timeString}`;
}

// Cash Flow Timeline Chart
function initializeCashFlowChart() {
  const ctx = document.getElementById("cashFlowChart").getContext("2d");
  const data = analyticsData.velocityData;

  if (!data || data.length === 0) {
    showNoDataMessage(
      "cashFlowChart",
      "No transaction data available for the selected period"
    );
    return;
  }

  const dates = data.map((item) => item.date);
  const spent = data.map((item) => Math.abs(item.spent || 0));
  const received = data.map((item) => Math.abs(item.received || 0));

  new Chart(ctx, {
    type: "line",
    data: {
      labels: dates,
      datasets: [
        {
          label: "Money Out",
          data: spent,
          borderColor: chartColors.danger,
          backgroundColor: "rgba(220, 53, 69, 0.1)",
          tension: 0.4,
          fill: true,
        },
        {
          label: "Money In",
          data: received,
          borderColor: chartColors.success,
          backgroundColor: "rgba(40, 167, 69, 0.1)",
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
          mode: "index",
          intersect: false,
          callbacks: {
            label: function (context) {
              return `${context.dataset.label}: GHC${context.raw.toFixed(2)}`;
            },
          },
        },
      },
      scales: {
        x: {
          type: "time",
          time: {
            unit:
              analyticsData.period === "7d"
                ? "day"
                : analyticsData.period === "30d"
                ? "week"
                : "month",
          },
          title: {
            display: true,
            text: "Date",
          },
        },
        y: {
          beginAtZero: true,
          title: {
            display: true,
            text: "Amount (GHC)",
          },
          ticks: {
            callback: function (value) {
              return "GHC" + value;
            },
          },
        },
      },
    },
  });
}

// Spending by Category Pie Chart
function initializeSpendingPieChart() {
  const ctx = document.getElementById("spendingPieChart").getContext("2d");
  const categoryData = analyticsData.categorySpending;

  // Filter only spending categories (negative amounts) and with actual spending
  const spendingCategories = categoryData.filter(
    (item) => item.amount < 0 && Math.abs(item.amount) > 0
  );

  if (spendingCategories.length === 0) {
    showNoDataMessage(
      "spendingPieChart",
      "No categorized spending data available"
    );
    return;
  }

  const labels = spendingCategories.map((item) => item.category);
  const data = spendingCategories.map((item) => Math.abs(item.amount));

  new Chart(ctx, {
    type: "doughnut",
    data: {
      labels: labels,
      datasets: [
        {
          data: data,
          backgroundColor: categoryColors,
          borderWidth: 2,
          borderColor: "#fff",
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
            label: function (context) {
              const value = context.raw;
              const total = context.dataset.data.reduce((a, b) => a + b, 0);
              const percentage = ((value / total) * 100).toFixed(1);
              return `${context.label}: GHC${value.toFixed(
                2
              )} (${percentage}%)`;
            },
          },
        },
      },
    },
  });
}

// Category Trends Chart
function initializeCategoryTrendsChart() {
  const ctx = document.getElementById("categoryTrendsChart").getContext("2d");
  const categoryTrends = analyticsData.categoryTrends;

  if (!categoryTrends || categoryTrends.length === 0) {
    showNoDataMessage(
      "categoryTrendsChart",
      "No category trend data available"
    );
    return;
  }

  // Prepare data for current vs previous period comparison
  const labels = [];
  const currentData = [];
  const previousData = [];

  categoryTrends.forEach((trend) => {
    if (
      trend.category &&
      (Math.abs(trend.current_amount) > 0 ||
        Math.abs(trend.previous_amount) > 0)
    ) {
      labels.push(trend.category);
      currentData.push(Math.abs(trend.current_amount));
      previousData.push(Math.abs(trend.previous_amount || 0));
    }
  });

  if (labels.length === 0) {
    showNoDataMessage(
      "categoryTrendsChart",
      "No category trend data available"
    );
    return;
  }

  new Chart(ctx, {
    type: "bar",
    data: {
      labels: labels,
      datasets: [
        {
          label: "Current Period",
          data: currentData,
          backgroundColor: chartColors.primary,
        },
        {
          label: "Previous Period",
          data: previousData,
          backgroundColor: chartColors.gray,
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
      },
      scales: {
        x: {
          title: {
            display: true,
            text: "Categories",
          },
        },
        y: {
          beginAtZero: true,
          title: {
            display: true,
            text: "Amount (GHC)",
          },
          ticks: {
            callback: function (value) {
              return "GHC" + value;
            },
          },
        },
      },
    },
  });
}

// Transaction Velocity Chart
function initializeVelocityChart() {
  const ctx = document.getElementById("velocityChart").getContext("2d");
  const velocityData = analyticsData.velocityData;

  if (!velocityData || velocityData.length === 0) {
    showNoDataMessage(
      "velocityChart",
      "No transaction velocity data available"
    );
    return;
  }

  const dates = velocityData.map((item) => item.date);
  const transactionCounts = velocityData.map((item) => item.count);

  new Chart(ctx, {
    type: "bar",
    data: {
      labels: dates,
      datasets: [
        {
          label: "Transactions per Day",
          data: transactionCounts,
          backgroundColor: chartColors.info,
          borderColor: chartColors.primary,
          borderWidth: 1,
        },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: {
          display: false,
        },
      },
      scales: {
        x: {
          type: "time",
          time: {
            unit: analyticsData.period === "7d" ? "day" : "week",
          },
          title: {
            display: true,
            text: "Date",
          },
        },
        y: {
          beginAtZero: true,
          title: {
            display: true,
            text: "Number of Transactions",
          },
          ticks: {
            stepSize: 1,
          },
        },
      },
    },
  });
}

// Top Recipients Chart
function initializeRecipientsChart() {
  const ctx = document.getElementById("recipientsChart").getContext("2d");
  const recipients = analyticsData.topRecipients;

  if (!recipients || recipients.length === 0) {
    return; // No chart needed if no recipients
  }

  // Prepare data for horizontal bar chart
  const labels = recipients.map((r) => {
    // Shorten long names for display
    const name = r.recipient;
    return name.length > 15 ? name.substring(0, 15) + "..." : name;
  });
  const data = recipients.map((r) => Math.abs(r.total));

  new Chart(ctx, {
    type: "bar",
    data: {
      labels: labels,
      datasets: [
        {
          label: "Total Sent",
          data: data,
          backgroundColor: categoryColors.slice(0, recipients.length),
          borderColor: chartColors.primary,
          borderWidth: 1,
        },
      ],
    },
    options: {
      indexAxis: "y",
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: {
          display: false,
        },
        tooltip: {
          callbacks: {
            title: function (tooltipItems) {
              const index = tooltipItems[0].dataIndex;
              return recipients[index].recipient;
            },
            label: function (context) {
              const index = context.dataIndex;
              const recipient = recipients[index];
              return [
                `Total: GHC${Math.abs(recipient.total).toFixed(2)}`,
                `Transactions: ${recipient.count}`,
                `Average: GHC${Math.abs(recipient.avg_amount).toFixed(2)}`,
              ];
            },
          },
        },
      },
      scales: {
        x: {
          beginAtZero: true,
          title: {
            display: true,
            text: "Amount (GHC)",
          },
        },
      },
    },
  });
}

// Helper function to show no data message
function showNoDataMessage(canvasId, message) {
  const canvas = document.getElementById(canvasId);
  const container = canvas.parentElement;

  container.innerHTML = `
        <div class="no-data">
            <i class="fas fa-chart-bar"></i>
            <p>${message}</p>
        </div>
    `;
}
