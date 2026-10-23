            <div
                x-cloak
                x-show="confirmationOpen || submitting"
                x-transition.opacity
                class="requisition-confirmation"
                @click.self="!submitting && cancelRequisitionConfirmation()"
                @keydown.escape.window="confirmationOpen && !submitting && cancelRequisitionConfirmation()"
            >
                <div x-show="!submitting" class="requisition-confirmation__dialog" role="dialog" aria-modal="true" aria-labelledby="requisitionConfirmationTitle" aria-describedby="requisitionConfirmationDescription">
                    <div class="requisition-confirmation__icon" aria-hidden="true"><i data-lucide="clipboard-check"></i></div>
                    <span class="requisition-confirmation__eyebrow">Final review</span>
                    <h5 id="requisitionConfirmationTitle">Submit this requisition?</h5>
                    <p id="requisitionConfirmationDescription">Once submitted, your request will be sent into the approval workflow.</p>

                    <div class="requisition-confirmation__summary">
                        <div><span>Request type</span><strong x-text="tierLabel"></strong></div>
                        <div><span>Total quantity</span><strong x-text="cartQuantity + ' item' + (cartQuantity === 1 ? '' : 's')"></strong></div>
                        <div class="wide"><span>Estimated total</span><strong x-text="'₱' + formatMoney(grandTotal)"></strong></div>
                    </div>

                    <div class="requisition-confirmation__actions">
                        <button type="button" class="btn btn-light border rounded-3" @click="cancelRequisitionConfirmation()">Review request</button>
                        <button type="button" class="btn btn-htc rounded-3 fw-bold" x-ref="confirmRequisitionButton" @click="confirmRequisitionSubmit()">
                            <i data-lucide="send" aria-hidden="true"></i> Submit requisition
                        </button>
                    </div>
                </div>

                <div x-show="submitting" class="requisition-confirmation__dialog requisition-confirmation__loading" role="status" aria-live="assertive">
                    <span class="requisition-loading-circle" aria-hidden="true"></span>
                    <h5>Submitting requisition…</h5>
                    <p class="mb-0">Please wait while your request is being recorded.</p>
                </div>
            </div>
