const videos = document.querySelectorAll('video');
  
  for(const video of videos){
    video.addEventListener('click', function(){
      console.log('cliked');
      if (video.paused){
        video.play();
      } else {
        video.pause();
      }
      
    });
  }
