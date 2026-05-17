const playPauseBtn = document.querySelector(".play-pause-btn")
const theaterBtn = document.querySelector(".theater-btn")
const fullScreenBtn = document.querySelector(".full-screen-btn")
const miniPlayerBtn = document.querySelector(".mini-player-btn")
const muteBtn = document.querySelector(".mute-btn")
const captionsBtn = document.querySelector(".captions-btn")
const speedBtn = document.querySelector(".speed-btn")
const currentTimeElem = document.querySelector(".current-time")
const totalTimeElem = document.querySelector(".total-time")
const previewImg = document.querySelector(".preview-img")
const thumbnailImg = document.querySelector(".thumbnail-img")
const volumeSlider = document.querySelector(".volume-slider")
const videoContainer = document.querySelector(".video-container")
const timelineContainer = document.querySelector(".timeline-container")
const video = document.querySelector("video")

document.addEventListener("keydown", (e) => {
  const tagName = document.activeElement.tagName.toLowerCase()

  if (tagName === "input" || tagName === "textarea") return

  const key = e.key.toLowerCase()
  switch (key) {
    case " ":
      if (tagName === "button") return
    case "k":
      togglePlayPause()
      break
    case "f":
      toggleFullScreen()
      break
    case "t":
      toggleTheater()
      break
    case "i":
      toggleMiniPlayer()
      break
    case "m":
      toggleMute()
      break
    case "arrowleft":
    case "j":
      skip(-5)
      break
    case "arrowright":
    case "l":
      skip(5)
      break
    case "c":
      toggleCaptions()
      break
  }
})

// Timeline

timelineContainer.addEventListener("mousemove", handleTimelineUpdate)
timelineContainer.addEventListener("mousedown", toggleScrubbing)
document.addEventListener("mouseup", e => {
  if (isScrubbing) {
    toggleScrubbing(e)
  }
})
document.addEventListener("mousemove", (e) => {
  if (isScrubbing) {
    handleTimelineUpdate(e)
  }
})

let isScrubbing = false
let wasPaused
function toggleScrubbing(e) {
  const { left, width } = timelineContainer.getBoundingClientRect()
  const percent = Math.min(1, Math.max(0, (e.clientX - left) / width))
  isScrubbing = (e.buttons & 1) === 1
  videoContainer.classList.toggle("scrubbing", isScrubbing)
  if (isScrubbing) {
    wasPaused = video.paused
    video.pause()
  } else {
    video.currentTime = percent * video.duration
    if (!wasPaused) {
      video.play()
    }
  }

  handleTimelineUpdate()
}

function handleTimelineUpdate(e) {
  const { left, width } = timelineContainer.getBoundingClientRect()
  const percent = Math.min(1, Math.max(0, (e.clientX - left) / width))
  const previewImageNumber = Math.max(1, Math.floor(percent * video.duration  / 10))
  const previewImage = `assets/previewImgs/preview${previewImageNumber}.jpg`
  previewImg.src = previewImage
  timelineContainer.style.setProperty("--preview-position", percent)

  if (isScrubbing) {
    e.preventDefault()
    thumbnailImg.src = previewImage
    timelineContainer.style.setProperty("--progress-position", percent)
  }
}

// Playback speed

speedBtn.addEventListener("click", changePlaybackSpeed)

function changePlaybackSpeed() {
  const currentSpeed = video.playbackRate
  let newSpeed = currentSpeed + 0.25
  if (newSpeed > 2) {
    newSpeed = 0.5
  }
  video.playbackRate = newSpeed
  speedBtn.textContent = `${newSpeed}x`
}

// Captions

const captions = video.textTracks[0]
captions.mode = "hidden"

captionsBtn.addEventListener("click", toggleCaptions)

function toggleCaptions() {
  const isHidden = captions.mode === "hidden"
  captions.mode = isHidden ? "showing" : "hidden"
  videoContainer.classList.toggle("captions", isHidden)
}

// Duration

function skip(seconds) {
  video.currentTime += seconds
}

video.addEventListener("loadedmetadata", () => {
  totalTimeElem.textContent = formatTime(video.duration)
})

video.addEventListener("timeupdate", () => {
  currentTimeElem.textContent = formatTime(video.currentTime)
  const percent = video.currentTime / video.duration
  timelineContainer.style.setProperty("--progress-position", percent)
})

function formatTime(time) {
  const minutes = Math.floor(time / 60)
  const seconds = Math.floor(time % 60)
  const hours = Math.floor(time / 3600)
  if (hours > 0) {
    return `${hours}:${minutes.toString().padStart(2, "0")}:${seconds.toString().padStart(2, "0")}`
  }

  return `${minutes}:${seconds.toString().padStart(2, "0")}`
}

// Volume

muteBtn.addEventListener("click", toggleMute)
volumeSlider.addEventListener("input", updateVolume)

function toggleMute() {
  video.muted = !video.muted
}

function updateVolume() {
  video.volume = volumeSlider.value
  video.muted = volumeSlider.value === 0
}

video.addEventListener("volumechange", () => {
  volumeSlider.value = video.volume
  let volumeLevel
  if (video.volume === 0 || video.muted) {
    volumeLevel = "muted"
  } else if (video.volume < 0.5) {
    volumeLevel = "low"
  } else {
    volumeLevel = "high"
  }
  videoContainer.dataset.volumeLevel = volumeLevel
})

// View mode
miniPlayerBtn.addEventListener("click", toggleMiniPlayer)
theaterBtn.addEventListener("click", toggleTheater)
fullScreenBtn.addEventListener("click", toggleFullScreen)

function toggleMiniPlayer() {
  if (videoContainer.classList.contains("mini-player")) {
    document.exitPictureInPicture()
  } else {
    video.requestPictureInPicture()
  }
}

function toggleTheater() {
  videoContainer.classList.toggle("theater")
}

function toggleFullScreen() {
  if (document.fullscreenElement == null) {
    videoContainer.requestFullscreen()
  } else {
    document.exitFullscreen()
  }
}

videoContainer.addEventListener("fullscreenchange", () => {
  if (document.fullscreenElement == null) {
    videoContainer.classList.remove("full-screen")
  } else {
    videoContainer.classList.add("full-screen")
  }
})

videoContainer.addEventListener("enterpictureinpicture", () => {
  videoContainer.classList.add("mini-player")
})

videoContainer.addEventListener("leavepictureinpicture", () => {
  videoContainer.classList.remove("mini-player")
})

// Play/Pause

playPauseBtn.addEventListener("click", togglePlayPause)
video.addEventListener("click", togglePlayPause)

function togglePlayPause() {
  if (video.paused) {
    video.play()
  } else {
    video.pause()
  }
}

video.addEventListener("play", () => {
  videoContainer.classList.remove("paused")
})

video.addEventListener("pause", () => {
  videoContainer.classList.add("paused")
})