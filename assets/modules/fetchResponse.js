async function fetchResponse(url) {
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
   return response;
} 

module.exports = { fetchResponse };