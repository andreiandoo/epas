import { Audio } from 'expo-av';

let successSound = null;
let errorSound = null;

export async function loadSounds() {
  try {
    const { sound: s1 } = await Audio.Sound.createAsync(
      require('../../assets/sounds/success.mp3')
    );
    successSound = s1;
  } catch (e) {
    // Sound file not available yet
  }

  try {
    const { sound: s2 } = await Audio.Sound.createAsync(
      require('../../assets/sounds/error.mp3')
    );
    errorSound = s2;
  } catch (e) {
    // Sound file not available yet
  }
}

export async function playSuccess() {
  try {
    if (successSound) {
      await successSound.replayAsync();
    }
  } catch (e) {
    // Ignore
  }
}

export async function playError() {
  try {
    if (errorSound) {
      await errorSound.replayAsync();
    }
  } catch (e) {
    // Ignore
  }
}

export async function unloadSounds() {
  try {
    if (successSound) await successSound.unloadAsync();
    if (errorSound) await errorSound.unloadAsync();
  } catch (e) {
    // Ignore
  }
}
