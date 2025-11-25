document.addEventListener('DOMContentLoaded', () => {
    
    

    // Toggle sub-menus
    const subButtons = document.querySelectorAll('.sub-btn');
    subButtons.forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            const subMenu = btn.nextElementSibling;
            subMenu.style.display = subMenu.style.display === 'block' ? 'none' : 'block';
            btn.querySelector('.dropdown').classList.toggle('rotate');
        });
    });
     // Angle-bar rotation
    document.querySelectorAll('.sub-btn').forEach((btn) => {
        btn.addEventListener('click', () => {
          btn.classList.toggle('open');
        });
      });


    // Calendar functionality
    const header = document.querySelector(".calendar h3");
    const dates = document.querySelector(".dates");
    const navs = document.querySelectorAll("#prev, #next");

    const months = [
        "January", "February", "March", "April", "May", "June",
        "July", "August", "September", "October", "November", "December"
    ];

    let date = new Date();
    let month = date.getMonth();
    let year = date.getFullYear();

    function renderCalendar() {
        const startDay = new Date(year, month, 1).getDay();
        const endDate = new Date(year, month + 1, 0).getDate();
        const endDay = new Date(year, month + 1, 0).getDay();
        const prevMonthEndDate = new Date(year, month, 0).getDate();

        let datesHtml = "";

        // Previous month's dates
        for (let i = startDay; i > 0; i--) {
            datesHtml += `<li class="inactive">${prevMonthEndDate - i + 1}</li>`;
        }

        // Current month's dates
        for (let i = 1; i <= endDate; i++) {
            const isToday = i === date.getDate() && month === new Date().getMonth() && year === new Date().getFullYear();
            datesHtml += `<li class="${isToday ? 'today' : ''}">${i}</li>`;
        }

        // Next month's dates
        for (let i = endDay; i < 6; i++) {
            datesHtml += `<li class="inactive">${i - endDay + 1}</li>`;
        }

        dates.innerHTML = datesHtml;
        header.textContent = `${months[month]} ${year}`;
    }

    navs.forEach(nav => {
        nav.addEventListener('click', (e) => {
            const btnId = e.target.id;

            if (btnId === "prev") {
                if (month === 0) {
                    month = 11;
                    year--;
                } else {
                    month--;
                }
            } else if (btnId === "next") {
                if (month === 11) {
                    month = 0;
                    year++;
                } else {
                    month++;
                }
            }

            date = new Date(year, month, date.getDate());
            renderCalendar();
        });
    });

    renderCalendar();
});
