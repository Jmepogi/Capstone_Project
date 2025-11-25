<?php
session_start();

require '../config/system_db.php'; // or include '../config/system_db.php';

$table = "tbl_proposal";



// Function to set flash messages
function setFlashMessage($type, $message) {
    $_SESSION['flash_message'] = ['type' => $type, 'message' => $message];
}

// Fetch approved proposals with additional details
$query = "SELECT 
            p.proposal_id, 
            p.title, 
            p.type, 
            p.organization, 
            p.president, 
            p.datetime_start,
            p.datetime_end, 
            p.venue,
            p.status,
            p.description 
          FROM tbl_proposal p
          WHERE p.status = 'Approved'
          ORDER BY p.datetime_start DESC";

$result = $connection->query($query);
$data = array();

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        // Format the dates
        $start_datetime = new DateTime($row['datetime_start']);
        $end_datetime = new DateTime($row['datetime_end']);
        
        $formatted_start = $start_datetime->format('Y-m-d\TH:i:s');
        $formatted_end = $end_datetime->format('Y-m-d\TH:i:s');
        
        // Create the actions column
        $actions = '<div class="btn-group" role="group">
                     <button type="button" class="btn btn-primary btn-sm view-btn" data-id="'.$row['proposal_id'].'">
                         <i class="material-icons">visibility</i>
                     </button>
                     <button type="button" class="btn btn-success btn-sm edit-btn" data-id="'.$row['proposal_id'].'">
                         <i class="material-icons">edit</i>
                     </button>
                   </div>';
        
        $data[] = array(
            "proposal_id" => $row['proposal_id'],
            "title" => $row['title'],
            "type" => $row['type'],
            "organization" => $row['organization'],
            "president" => $row['president'],
            "start" => $formatted_start,
            "end" => $formatted_end,
            "venue" => $row['venue'],
            "description" => $row['description'],
            "status" => $row['status'],
            "actions" => $actions
        );
    }
}

// Prepare calendar events data
$calendarEvents = array_map(function($row) {
    return [
        'id' => $row['proposal_id'],
        'title' => $row['title'],
        'start' => $row['start'],
        'end' => $row['end'],
        'extendedProps' => [
            'organization' => $row['organization'],
            'president' => $row['president'],
            'status' => $row['status'],
            'venue' => $row['venue'],
            'description' => $row['description'],
            'type' => $row['type']
        ]
    ];
}, $data);

$connection->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Management</title>
    <link rel="icon" href="../images/login/cefi-logo.png" type="image/png">
    
    <!-- System CSS -->
    <link rel="stylesheet" href="../resources/css/user.css">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- FullCalendar v5 CSS -->
    <link rel="stylesheet" href='https://cdn.jsdelivr.net/npm/fullcalendar@5.10.1/main.min.css'>
    
    <!-- Material Icons -->
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0" />

    
    <style>
        .calendar-container {
            display: flex;
            flex-direction: row;
            justify-content: space-between;
            padding: 20px;
            box-shadow: none;
            margin-top: -55px; /* Adjust this value for more or less space */
        }


        .calendar-container {
            display: flex;
            flex-direction: row;
            gap: 20px; /* Adds space between calendar and event card */
            padding: 20px;
            background: #fff;
            border-radius: 10px;
        }

        .calendar-container .calendar {
            width: 70%; /* Calendar takes 3/4 of the container */
            background: #fff; /* Ensures a clean background for the calendar */
            border-radius: 10px;
            padding: 10px; /* Optional padding for spacing inside the calendar */
        }

        .calendar-container .event-card {
            width: 30%; 
            border-radius: 10px;
            max-height: 600px; 
        }


        #calendar {
            width: 100%;
            height: 700px;
        }

        #event-card {
            width: 100%;
            height: 700px;
            border: none;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            padding: 0;
            margin-top: 10px;
            border-radius: 12px;
            transition: all 0.3s ease;
            background-color: #ffffff;
            overflow: hidden;
        }
       /* FullCalendar: Date numbers */
        .fc-daygrid-day-number {
            color: #333 !important; /* Dark color for the date numbers */
            text-decoration: none !important; /* Remove underline */
            font-weight: 500;
            text-align: center;
           
           
            border-radius: 50%; /* Optional: Round the date number */
        }

        /* FullCalendar: Day headers */
        .fc .fc-header-toolbar .fc-day-header {
            color: #333 !important; /* Dark color for day names */
            font-weight: bold;
            text-align: center;
            padding: 5px;
            background-color: #f8f9fa; /* Light background color for day headers */
            border-bottom: 2px solid #ddd; /* Subtle border at the bottom */
        }

        /* Limit the height of event elements */
        .fc-content {
            max-height: 50px; /* Adjust this as needed */
            overflow: hidden; /* Hide overflow content */
            text-overflow: ellipsis; /* Add ellipsis for overflow text */
            padding: 5px; /* Padding inside events */
            color: #333;    
        }

        /* Allow event titles to wrap properly inside the event */
        .fc-title {
            white-space: normal; /* Allow text to wrap */
            word-wrap: break-word;
            font-size: 0.9em; /* Adjust font size for better readability */
            font-weight: 500; /* Slightly bold for titles */
            color: #333; 
        }
            

        /* Extra-Curricular Activity Proposal */
        .fc-event.ec-activity-proposal {
            background-color: #f5c6cb; /* Soft Red */
            color: #721c24; /* Dark Red */
            border: 1px solid #f1a7a2; /* Light Red Border */
        }

        /* Co-Curricular Activity Proposal */
        .fc-event.cc-activity-proposal {
            background-color: #d1e7dd; /* Soft Green */
            color: #0f5132; /* Dark Green */
            border: 1px solid #badbcc; /* Light Green Border */
        }

        /* Extra-Curricular Activity Proposal (Community Project) */
        .fc-event.ec-community-project {
            background-color: #fff3cd; /* Soft Yellow */
            color: #856404; /* Dark Brown */
            border: 1px solid #ffecb5; /* Light Yellow Border */
        }

        /* Co-Curricular Activity Proposal (Community Project) */
        .fc-event.cc-community-project {
            background-color: #cfe2ff; /* Soft Blue */
            color: #003366; /* Dark Blue */
            border: 1px solid #9ecbff; /* Light Blue Border */
        }

        /* Header (toolbar) buttons */
        .fc-toolbar .fc-button {
        background-color: #135626;
        border: none;
        color: white;
        }

        .fc-toolbar .fc-button:hover {
        background-color: #2B6D3D;
        }
        
    </style>

    <!-- Pass PHP data to JavaScript -->
    <script>
        window.calendarEvents = <?php echo json_encode($calendarEvents); ?>;
    </script>
</head>
<body>
    <header class="cefi-header">
        <img src="../images/login/cefi-logo.png" alt="cefi-logo" class="logo">
        <div>
            <h1 class="osa">OFFICE FOR STUDENT AFFAIRS</h1>
            <p class="system">Management Information System</p>
        </div>
    </header>
    
    <div class="wrapper">
        <?php include('../resources/utilities/sidebar/signatories_sidebar.php'); ?>

        <div class="d-content">
            <div class="content-header">
                <h2 class="d-title" style="color:#fff;">DASHBOARD </h2>
                <div class="menu-icon">
                   
                </div>
            </div>

            <div class="dashboard-wrapper">
                <div class="calendar-container">
                    <div class="calendar">
                        <div id="calendar"></div>
                    </div>
                    <div class="event-card">
                        <div id="event-card" class="card">
                            <!-- Event details will be displayed here -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- jQuery (necessary for Bootstrap 5's JavaScript components) -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    
    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- FullCalendar v5 JS -->
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@5.10.1/main.min.js'></script>
    <!-- Moment.js (used by FullCalendar and for formatting dates) -->
    <script src="https://cdn.jsdelivr.net/npm/moment@2.29.1/moment.min.js"></script>

    <script src="../resources/js/universal.js"></script>
    <script src="../resources/js/calendar.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var calendarEl = document.getElementById('calendar');
            var calendar = new FullCalendar.Calendar(calendarEl, {
                headerToolbar: {
                    left: 'title',
                },
                initialView: 'dayGridMonth',
                dayMaxEvents: 2, 
                events: window.calendarEvents || [], // Use empty array if no events
                eventClick: function (info) {
                    displayEventDetails(info.event);
                },
                dateClick: function (info) {
                    const clickedDate = moment(info.dateStr).format('YYYY-MM-DD');
                    const eventsOnDate = calendar.getEvents().filter(event =>
                        moment(event.start).format('YYYY-MM-DD') === clickedDate
                    );
                    displayEventsForDate(eventsOnDate, info.dateStr);
                },
                // Add custom class names based on event type
                eventClassNames: function (arg) {
                    var eventType = arg.event.extendedProps.type;
                    
                    if (eventType === 'Extra-Curricular Activity Proposal') {
                        return ['ec-activity-proposal']; // Custom class for this type
                    } else if (eventType === 'Co-Curricular Activity Proposal') {
                        return ['cc-activity-proposal']; // Custom class for this type
                    } else if (eventType === 'Extra-Curricular Activity Proposal (Community Project)') {
                        return ['ec-community-project']; // Custom class for this type
                    } else if (eventType === 'Co-Curricular Activity Proposal (Community Project)') {
                        return ['cc-community-project']; // Custom class for this type
                    } else {
                        return []; // Default class
                    }
                },
                eventContent: function (arg) {
                    return {
                        html: `
                            <div class="fc-content">
                                <div class="fc-title">${arg.event.title}</div>
                                <div class="fc-description small">${arg.event.extendedProps.organization}</div>
                            </div>
                        `
                    };
                }
            });


            function displayEventDetails(event) {
                const start = moment(event.start);
                const end = event.end ? moment(event.end) : null;

                let timeDisplay;

                if (end && start.isSame(end, 'day')) {
                    const startTime = start.format('h:mm A');
                    const endTime = end.format('h:mm A');
                    timeDisplay = `${startTime} - ${endTime}`;
                } else if (end) {
                    const startDateTime = start.format('MMM D, h:mm A');
                    const endDateTime = end.format('MMM D, h:mm A');
                    timeDisplay = `${startDateTime} - ${endDateTime}`;
                } else {
                    timeDisplay = start.format('MMM D, h:mm A') + ' - Not specified';
                }

                // Extended properties
                const organization = event.extendedProps.organization || 'Not specified';
                const description = event.extendedProps.description || 'No description provided';
                const venue = event.extendedProps.venue || 'Not specified';
                const eventType = event.extendedProps.type || 'Not specified';

                const cardContent = `
                    <div class="card-body p-4" style="background: linear-gradient(to bottom, #f8f9fa, #ffffff); border-radius: 10px;">
                        <!-- Time Banner (replacing separate date) -->
                        <div class="text-center mb-3 py-2" style="background-color: #f0f5f1; border-radius: 8px; border-left: 4px solid #417E52;">
                            <span style="color: #417E52; font-weight: 500; font-size: 0.9rem;">
                                <i class="material-icons" style="font-size: 1rem; vertical-align: middle; margin-right: 4px;">event</i>
                                ${timeDisplay}
                            </span>
                        </div>
                        
                        <!-- Event Title -->
                        <h5 class="mb-2" style="font-weight: 600; color: #343a40; font-size: 1rem;">${event.title}</h5>
                        <p style="font-size: 0.8em; font-weight: 400; color: #6c757d;">${eventType}</p>

                        <!-- Info Cards -->
                        <div class="row mb-3 g-2">
                            <!-- Organization Card -->
                            <div class="col-12">
                                <div class="d-flex align-items-center p-2" style="background-color: #fff; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.08);">
                                    <i class="material-icons me-2" style="color: #417E52; font-size: 1.2rem;">groups</i>
                                    <div>
                                        <div style="font-size: 0.75rem; color: #495057; font-weight: 500;">Organization</div>
                                        <div style="font-weight: 600; color: #212529;">${organization}</div>
                                    </div>
                                </div>
                            </div>

                            <!-- Venue Card -->
                            <div class="col-md-6">
                                <div class="d-flex align-items-center h-100 p-2" style="background-color: #fff; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.08);">
                                    <i class="material-icons me-2" style="color: #417E52; font-size: 1.2rem;">location_on</i>
                                    <div>
                                        <div style="font-size: 0.75rem; color: #495057; font-weight: 500;">Venue</div>
                                        <div style="font-weight: 600; color: #212529;">${venue}</div>
                                    </div>
                                </div>
                            </div>

                            <!-- Time Card (already covered in banner, but optional to keep here too) -->
                            <div class="col-md-6">
                                <div class="d-flex align-items-center h-100 p-2" style="background-color: #fff; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.08);">
                                    <i class="material-icons me-2" style="color: #417E52; font-size: 1.2rem;">schedule</i>
                                    <div>
                                        <div style="font-size: 0.75rem; color: #495057; font-weight: 500;">Time</div>
                                        <div style="font-weight: 600; color: #212529;">${timeDisplay}</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Description -->
                        <div class="mt-3 p-3" style="background-color: #fff; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.08);">
                            <h6 style="font-weight: 600; color: #212529; font-size: 0.9rem;">Description</h6>
                            <p style="color: #495057; font-size: 0.9rem; line-height: 1.5; text-align: justify; max-height: 200px; overflow-y: auto; padding-right: 5px;">
                                ${description}
                            </p>
                        </div>
                    </div>
                `;

                const eventCard = document.getElementById('event-card');
                eventCard.innerHTML = cardContent;
                eventCard.style.display = 'block';
                eventCard.style.boxShadow = '2px 2px 10px rgba(0, 0, 0, 0.3)';
                eventCard.style.borderRadius = '12px';
                eventCard.style.overflow = 'hidden';

                eventCard.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }


            function displayEventsForDate(events, date) {
                const eventCard = document.getElementById('event-card');
                const formattedDate = moment(date).format('dddd, MMMM D, YYYY');
                
                // Check if there are no events for the selected date
                if (events.length === 0) {
                    eventCard.innerHTML = `
                        <div class="card-body p-4" style="background: linear-gradient(to bottom, #f8f9fa, #ffffff); border-radius: 10px;">
                            <!-- Date banner -->
                            <div class="text-center mb-3 py-2" style="background-color: #f0f5f1; border-radius: 8px; border-left: 4px solid #417E52;">
                                <span style="color: #417E52; font-weight: 500; font-size: 0.9rem;">
                                    <i class="material-icons" style="font-size: 1rem; vertical-align: middle; margin-right: 4px;">event</i>
                                    ${formattedDate}
                                </span>
                            </div>
                            
                            <div class="text-center py-5">
                                <div class="mb-3">
                                    <i class="material-icons" style="font-size: 4rem; color: #e9ecef;">event_busy</i>
                                </div>
                                <h5 style="font-weight: 600; color: #343a40;">No Events Scheduled</h5>
                                <p style="color: #6c757d; font-size: 0.9rem;">There are no activities planned for this date.</p>
                            </div>
                        </div>
                    `;
                } else {
                    let eventsHtml = `
                        <div class="card-body p-4" style="background: linear-gradient(to bottom, #f8f9fa, #ffffff); border-radius: 10px; max-height: 700px; overflow-y: auto;">
                            <!-- Date banner -->
                            <div class="text-center mb-4 py-2" style="background-color: #f0f5f1; border-radius: 8px; border-left: 4px solid #417E52; position: sticky; top: 0; z-index: 1000;">
                                <span style="color: #417E52; font-weight: 500; font-size: 0.9rem;">
                                    <i class="material-icons" style="font-size: 1rem; vertical-align: middle; margin-right: 4px;">event</i>
                                    ${formattedDate}
                                </span>
                            </div>
                            
                            <h5 class="mb-3" style="font-weight: 600; color: #343a40; font-size: 1rem;">
                                <i class="material-icons" style="font-size: 1.2rem; vertical-align: middle; margin-right: 4px; color: #417E52;">list</i>
                                ${events.length} Event${events.length > 1 ? 's' : ''} Today
                            </h5>
                            
                            <div class="event-list">
                    `;

                    // Loop through the events and create the detailed layout for each event
                    events.forEach((event, index) => {
                        const startTime = moment(event.start).format('h:mm A');
                        const endTime = event.end ? moment(event.end).format('h:mm A') : 'Not specified';
                        const organization = event.extendedProps.organization || 'Not specified';
                        const description = event.extendedProps.description || 'No description provided';
                        const venue = event.extendedProps.venue || 'Not specified';
                        const eventType = event.extendedProps.type || 'No type specified';
                        
                        // Determine the badge color based on event type
                        let badgeColor = '#417E52'; // Default green
                        if (eventType.includes('Extra-Curricular')) {
                            badgeColor = eventType.includes('Community') ? '#e6c300' : '#dc3545'; // Yellow for community, red for regular
                        } else if (eventType.includes('Co-Curricular')) {
                            badgeColor = eventType.includes('Community') ? '#0d6efd' : '#198754'; // Blue for community, green for regular
                        }

                        // Event card HTML structure
                        eventsHtml += `
                            <div class="event-item mb-4 p-3" style="background-color: #fff; border-radius: 8px; box-shadow: 0 2px 6px rgba(0,0,0,0.06); border-left: 3px solid #417E52;">
                                <!-- Event header with title and type -->
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <h6 style="font-weight: 600; color: #343a40; margin-bottom: 4px;">${event.title}</h6>
                                    <span style="background-color: #f0f5f1; font-weight: 500; font-size: 0.75rem; padding: 4px 8px; border-radius: 4px; color: #417E52; border: 1px solid #d1e7dd;">
                                        ${eventType.split(' ')[0]} ${eventType.includes('Community') ? '(Comm)' : ''}
                                    </span>
                                </div>
                                
                                <!-- Info row -->
                                <div class="d-flex flex-wrap mb-2" style="font-size: 0.85rem;">
                                    <!-- Organization -->
                                    <div class="me-3 mb-2 d-flex align-items-center">
                                        <i class="material-icons me-1" style="color: #417E52; font-size: 1rem;">groups</i>
                                        <span style="color: #495057;">${organization}</span>
                                    </div>
                                    
                                    <!-- Venue -->
                                    <div class="me-3 mb-2 d-flex align-items-center">
                                        <i class="material-icons me-1" style="color: #417E52; font-size: 1rem;">location_on</i>
                                        <span style="color: #495057;">${venue}</span>
                                    </div>
                                    
                                    <!-- Time -->
                                    <div class="mb-2 d-flex align-items-center">
                                        <i class="material-icons me-1" style="color: #417E52; font-size: 1rem;">schedule</i>
                                        <span style="color: #495057;">${startTime} - ${endTime}</span>
                                    </div>
                                </div>
                                
                                <!-- Description (collapsible) -->
                                <div class="mt-2">
                                    <button class="btn btn-sm w-100 text-start" type="button" data-bs-toggle="collapse" 
                                            data-bs-target="#description-${index}" aria-expanded="false" style="background-color: #f8f9fa; color: #6c757d; font-size: 0.85rem;">
                                        <i class="material-icons" style="font-size: 0.9rem; vertical-align: middle; color: #417E52;">description</i>
                                        View Description
                                    </button>
                                    <div class="collapse mt-2" id="description-${index}">
                                        <div class="p-2" style="background-color: #f8f9fa; border-radius: 6px; font-size: 0.85rem; color: #495057; max-height: 150px; overflow-y: auto;">
                                            ${description}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        `;
                    });

                    eventsHtml += `
                            </div>
                        </div>
                    `;
                    eventCard.innerHTML = eventsHtml;
                }
                
                eventCard.style.display = 'block';
                eventCard.style.boxShadow = '0 4px 12px rgba(0,0,0,0.1)';
                eventCard.style.borderRadius = '12px';
                eventCard.style.overflow = 'hidden';
                eventCard.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
            calendar.render();
        });
    </script>
    
</body>
</html>