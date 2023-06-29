import { Controller } from '@hotwired/stimulus';

import { Modal } from 'bootstrap';

export default class extends Controller {
   static targets = ['modal','spinner','resultText','button', 'toggleOn', 'toggleOff'];
   static values = {
   };

   modal = null;
   originalMessage = null;

   connect() {
      this.originalMessage = this.resultTextTarget.innerHTML;
   }

   async fetchResponse(url) {
      this.showSpinner();
      this.showModal();
      const response = await fetch(url, {
         redirect: 'follow',
      }).then((response)=>{
         if (response.redirected) {
            window.location.reload();
         } else {
            if (response.ok) {
               return response.json();
            } else {
               console.log(response);
               return Promise.reject(response);
            }
         }
      }).catch(function (response) {
         if (response.redirected) {
            window.location.reload();
         } else if ( response.status === 422 ) {
            return response.json();
         }
      });
      // We should a response even on errors. But this way we avoid errors.
      if (!response) {
         return;
      }
      console.log(response);
      this.hideSpinner();
      this.updateMessage(response);
      return response;
   } 

   async onUnlock(event) {
      event.preventDefault();
      const url = event.currentTarget.href;
      this.fetchResponse(url);
   }

   async onActivateOfficeMode(event) {
      event.preventDefault();
      let url = event.currentTarget.href;
      this.showSpinner();
      this.showModal();
      const response = await this.fetchResponse(url);
      if (response.status == 'success') {
         this.toggleOnTarget.classList.remove('d-none');
         this.toggleOffTarget.classList.add('d-none');
      }
   }

   async onDeactivateOfficeMode(event) {
      event.preventDefault();
      const url = event.currentTarget.href;
      this.showSpinner();
      this.showModal();
      const response = await this.fetchResponse(url);
      if (response.status == 'success') {
         this.toggleOnTarget.classList.add('d-none');
         this.toggleOffTarget.classList.remove('d-none');
      }
   }

   showModal(event) {
      if (this.modal === null) {
         this.modal = new Modal(this.modalTarget);
      }
      this.modal.show();
   }

   hideModal(event) {
      this.modal.hide();
   }

   hideSpinner() {
      this.spinnerTarget.classList.add('d-none');
   }

   showSpinner() {
      this.spinnerTarget.classList.remove('d-none');
   }

   updateMessage(json) {
      if (json.status != 'success') {
         this.resultTextTarget.classList.add('alert-danger');
      } else {
         this.resultTextTarget.classList.add('alert-success');
      }
      this.resultTextTarget.innerHTML=json.message;
      this.resultTextTarget.classList.remove('alert-secondary');
   }

   resetMessage() {
      this.hideModal();
      this.resultTextTarget.innerHTML=this.originalMessage;
      this.resultTextTarget.classList.remove('alert-success');
      this.resultTextTarget.classList.remove('alert-danger');
      this.resultTextTarget.classList.add('alert-secondary');
   }
}
