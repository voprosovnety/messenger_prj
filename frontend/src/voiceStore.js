import { reactive } from 'vue'

export const SPEEDS = [1, 1.5, 2, 0.5]
export const SPEEDS_DISPLAY = [0.5, 1, 1.5, 2]

const _storedSpeed = parseFloat(localStorage.getItem('audioSpeed') || '1')
const _storedVol = parseFloat(localStorage.getItem('audioVol') || '1')

export const voiceStore = reactive({
  src: null,
  sender: '',
  playing: false,
  current: 0,
  duration: 0,
  speed: SPEEDS.includes(_storedSpeed) ? _storedSpeed : 1,
  vol: (_storedVol >= 0 && _storedVol <= 1) ? _storedVol : 1,
  _play: null,
  _pause: null,
  _seek: null,
  _setSpeed: null,
  _setVol: null,
  _stop: null,
})
