import { Controller } from '@hotwired/stimulus';

import { Modal } from 'bootstrap';

export default class extends Controller {
   modal = null;
   originalMessage = null;
   static targets = ['modal', 'resultText', 'spinner', 'restoreButton'];
   static values = {
   };

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
      this.hideSpinner();
      return response;
   } 

   async onShow(event) {
      event.preventDefault();
      console.log('onShow');
      const url = event.currentTarget.href;
      const response = await this.fetchResponse(url);
      console.log(response);
      this.showDetails(response, true);
   }

   async onRestore(event) {
      event.preventDefault();
      console.log('onRestore');
      const url = event.currentTarget.href;
     const response = await this.fetchResponse(url);
      console.log(response);
      this.showDetails(response, false);
      if (response.status == 'success') {
         this.restoreButtonTarget.classList.add('d-none');
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

   showDetails(json, multiple) {
      if (json.status != 'success') {
         this.resultTextTarget.classList.add('alert-danger');
      } else {
         this.resultTextTarget.classList.remove('alert-secondary');
      }
      this.resultTextTarget.innerHTML='';
      if ( multiple ) {
         this.resultTextTarget.classList.remove('text-center');
         this.resultTextTarget.classList.add('text-start');
         Object.entries(json.message).forEach(([clave, valor]) => {
            this.resultTextTarget.innerHTML+='<b>'+clave+'</b>:&nbsp;'+valor+'<br/>';
         });      
      } else {
         this.resultTextTarget.classList.add('alert-success');
         this.resultTextTarget.innerHTML=json.message;
      }
   }

   resetModal() {
      this.resultTextTarget.innerHTML=this.originalMessage;
      this.resultTextTarget.classList.remove('alert-success');
      this.resultTextTarget.classList.remove('alert-danger');
      this.resultTextTarget.classList.add('alert-secondary');
      this.resultTextTarget.classList.remove('text-start');
      this.resultTextTarget.classList.add('text-center');
      this.spinnerTarget.classList.remove('d-none');
   }

  
}