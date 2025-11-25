document.addEventListener('DOMContentLoaded', function() {
    // Get current year
    var currentYear = new Date().getFullYear();
    
    // Number of years to generate in the past and future
    var yearRange = 20;

    // Get the year select element
    var yearSelect = document.getElementById('yearFilter');
    
    // Generate academic year options
    for (var i = -yearRange; i <= yearRange; i++) {
        var startYear = currentYear + i;
        var endYear = startYear + 1;
        var option = document.createElement('option');
        option.value = startYear + '-' + endYear;
        option.textContent = startYear + '-' + endYear;
        yearSelect.appendChild(option);
    }

    // Automatically select the current academic year
    yearSelect.value = (currentYear - 1) + '-' + currentYear;

    var goodMoralCtx = document.getElementById('goodMoralChart').getContext('2d');
    var goodMoralChart;

    // Chart rendering function
    function renderChart(months, counts) {
        if (goodMoralChart) {
            goodMoralChart.destroy(); // Destroy previous chart instance
        }
        goodMoralChart = new Chart(goodMoralCtx, {
            type: 'bar',
            data: {
                labels: months,
                datasets: [{
                    label: 'Good Moral Requests',
                    data: counts,
                    backgroundColor: '#135626',
                    borderWidth: 1,
                    borderRadius: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: { grid: { display: false }},
                    y: { beginAtZero: true }
                },
                plugins: { legend: { display: false } }
            }
        });
    }

    // Render initial chart with PHP data
    renderChart(monthsData, goodMoralCountsData);

    // Filter button event listener
    document.getElementById('applyFilter').addEventListener('click', function(event) {
        event.preventDefault(); // Prevent default form submission

        var courseFilter = document.getElementById('courseFilter').value;
        var monthFilter = document.getElementById('monthFilter').value;
        var yearFilter = document.getElementById('yearFilter').value;

        // AJAX request to fetch filtered data
        var xhr = new XMLHttpRequest();
        xhr.open('POST', '../resources/utilities/functions/goodmoral_chart_function.php', true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.onload = function() {
            if (xhr.status === 200) {
                var response = JSON.parse(xhr.responseText);
                renderChart(response.months, response.counts); // Re-render chart with new data
            }
        };

        // Send filter data
        xhr.send('course=' + courseFilter + '&month=' + monthFilter + '&year=' + yearFilter);
    });
});
