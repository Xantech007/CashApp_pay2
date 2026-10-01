document.querySelectorAll('.video-container').forEach((container, index, containers) => {
    const video = container.querySelector('.video');
    const playButton = container.querySelector('.play-button');
    const nextButton = container.querySelector('.next-button');

    playButton.addEventListener('click', () => {
        if (video.paused) {
            video.play();
            playButton.textContent = '⏸️';
        } else {
            video.pause();
            playButton.textContent = '▶️';
        }
    });

    nextButton.addEventListener('click', () => {
        video.pause();
        playButton.textContent = '▶️';
        if (index < containers.length - 1) {
            const nextContainer = containers[index + 1];
            const nextVideo = nextContainer.querySelector('.video');
            const nextPlayButton = nextContainer.querySelector('.play-button');
            nextVideo.play();
            nextPlayButton.textContent = '⏸️';
            nextVideo.scrollIntoView({ behavior: 'smooth' });
        }
    });
});
