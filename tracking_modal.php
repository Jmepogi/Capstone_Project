<!-- Tracking Modal -->
                <div class="modal fade" id="trackingModal" tabindex="-1" aria-labelledby="trackingModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered modal-"> <!-- Add 'modal-lg' for larger screens -->
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="trackingModalLabel">Request Tracking</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <form id="trackingForm">
                                    
                                    <!-- Lottie animation inside the modal -->
                                    <div class="d-flex justify-content-center mb-3">
                                        <dotlottie-player 
                                            src="https://lottie.host/6864ec13-011b-450c-915d-86c0aa689178/iaCQThFOMb.json" 
                                            background="transparent" 
                                            speed="1" 
                                            style="max-width: 100%; width: 250px; height: 235px;"  
                                            loop 
                                            autoplay>
                                        </dotlottie-player>
                                    </div>
                                    <!-- Alert placeholder should be near the top to display alerts properly -->
                                    <div id="alertPlaceholder"></div>
                                    <div class="mb-3">
                                        <label for="tracking_number" class="form-label">Tracking Number</label>
                                        <input type="text" class="form-control" id="tracking_number" name="tracking_number" required>
                                    </div> 
                                    
                                    <div id="trackingDetails" class="mt-3">
                                        <div class="progress mb-3">
                                            <div id="progressBar" class="progress-bar" role="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">Pending</div>
                                        </div>
                                    </div>
                                    <div id="requestDetails"></div>
                                    
                                    <div class="text-end">
                                        <button type="button" class="btn btn-success " style=" width:100px;"id="trackButton" >Track</button> <!-- Responsive button width -->
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>