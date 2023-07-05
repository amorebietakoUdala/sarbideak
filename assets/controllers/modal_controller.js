import { Controller } from '@hotwired/stimulus';

import { Modal } from 'bootstrap';

export default class extends Controller {
   static targets = ['modal','spinner','resultText'];
   static values = {
   };

   modal = null;
   originalMessage = null;

   connect() {
      this.originalMessage = this.resultTextTarget.innerHTML;
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

   showDetails(json) {
      this.resultTextTarget.innerHTML='';
      if ( typeof json.message === 'object' ) {
         this.resultTextTarget.classList.remove('alert-secondary');
         this.resultTextTarget.classList.remove('text-center');
         this.resultTextTarget.classList.add('text-start');
         Object.entries(json.message).forEach(([clave, valor]) => {
            this.resultTextTarget.innerHTML+='<b>'+clave+'</b>:&nbsp;'+valor+'<br/>';
         }); 
      } else {
         if (json.status != 'success') {
            this.resultTextTarget.classList.add('alert-danger');
         } else {
            this.resultTextTarget.classList.add('alert-success');
         }
         this.resultTextTarget.innerHTML=json.message;
      }
   }

   resetMessage() {
      this.resultTextTarget.innerHTML=this.originalMessage;
      this.resultTextTarget.classList.remove('alert-success');
      this.resultTextTarget.classList.remove('alert-danger');
      this.resultTextTarget.classList.add('alert-secondary');
      this.resultTextTarget.classList.remove('text-start');
      this.resultTextTarget.classList.add('text-center');
      this.spinnerTarget.classList.remove('d-none');
      }

   startModal(e) {
      this.showModal();
      this.showSpinner();
   }

   endModal(e) {
      this.hideSpinner();
      this.showDetails(e.detail);
   }
}