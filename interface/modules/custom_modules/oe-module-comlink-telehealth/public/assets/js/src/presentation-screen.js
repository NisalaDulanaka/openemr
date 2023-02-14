export class PresentationScreen
{
    /**
     *
     * @type HTMLVideoElement
     */
    videoElement = null;

    /**
     *
     * @type CallerSlot
     */
    callerSlot = null;

    constructor(domNodeId)
    {
        /**
         *
         * @type HTMLVideoElement
         */
        this.videoElement = document.getElementById(domNodeId);
        if (!this.videoElement) {
            throw new Error("Failed to find presentation screen dom node with id " + domNodeId);
        }
    }

    updateCallerSlotScreen() {
        if (this.callerSlot && this.callerSlot.getCurrentCallStream() != null) {
            // will this be true on every video element?
            // TODO: @adunsulag test on this.
            if (this.videoElement && this.callerSlot.getCurrentCallStream() != this.videoElement.srcObject) {
                this.videoElement.srcObject = this.callerSlot.getCurrentCallStream();
                this.videoElement.play(); // TODO: do we need this?
            }
        }
    }

    attach(callerSlot) {
        if (this.callerSlot != null) {
            // nothing to do here, just return
            if (this.callerSlot === callerSlot
                || this.callerSlot.getRemotePartyId() == callerSlot.getRemotePartyId()) {
                this.updateCallerSlotScreen();
                return;
            }
        }
        // if we have something let's remove it.
        if (this.callerSlot) {
            this.detach();
        }

        if (callerSlot && callerSlot.getCurrentCallStream() != null) {
            let displayTitle = callerSlot.getParticipant() ? callerSlot.getParticipant().callerName : "";
            this.videoElement.srcObject = callerSlot.getCurrentCallStream();
            this.videoElement.play();
            this.videoElement.title = displayTitle;
            this.callerSlot = callerSlot;
        }
    }

    hide() {
        if (this.videoElement) {
            this.videoElement.classList.add('d-none');
        }
    }

    show() {
        if (this.videoElement) {
            this.videoElement.classList.remove('d-none');
        }
    }

    getVideoElement() {
        return this.videoElement;
    }

    detach() {
        this.callerSlot = null;
        this.videoElement.srcObject = null;
    }
}