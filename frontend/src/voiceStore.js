import { reactive } from 'vue'

export const SPEEDS = [1, 1.5, 2, 0.5]

const _stored = parseFloat(localStorage.getItem('audioSpeed') || '1')

export const voiceStore = reactive({
  src: null,
  sender: '',
  playing: false,
  current: 0,
  duration: 0,
  speed: SPEEDS.includes(_stored) ? _stored : 1,
  vol: 1,
  _play: null,
  _pause: null,
  _seek: null,
  _setSpeed: null,
  _setVol: null,
  _stop: null,
})
