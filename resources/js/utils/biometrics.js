/**
 * WebAuthn & Biometric Authentication Utility
 * Precision hardware detection for Face ID, Touch ID, Fingerprint, and Windows Hello.
 */

export const getDevicePlatform = () => {
  const userAgent = navigator.userAgent || navigator.vendor || window.opera || ''
  if (/iPad|iPhone|iPod/.test(userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1)) {
    return 'ios'
  }
  if (/android/i.test(userAgent)) {
    return 'android'
  }
  return 'web'
}

/**
 * Accurately detect the specific biometric hardware type of the current device
 */
export const getBiometricType = () => {
  const ua = navigator.userAgent || navigator.vendor || ''
  const isIOS = /iPad|iPhone|iPod/.test(ua) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1)
  const isMac = /Macintosh|MacIntel/.test(ua) && !isIOS
  const isAndroid = /android/i.test(ua)
  const isWindows = /windows/i.test(ua)

  if (isIOS) {
    const minDim = Math.min(window.screen.width, window.screen.height)
    const maxDim = Math.max(window.screen.width, window.screen.height)
    // iPhone X and later notch/dynamic-island devices (aspect ratio >= 2.16) have Face ID
    const isFaceIdDevice = (maxDim / minDim >= 2.1) && !/iPad/.test(ua)

    if (isFaceIdDevice) {
      return {
        type: 'face_id',
        label: 'Đăng nhập bằng Face ID',
        icon: 'face_id'
      }
    }
    return {
      type: 'touch_id',
      label: 'Đăng nhập bằng Touch ID',
      icon: 'touch_id'
    }
  }

  if (isMac) {
    return {
      type: 'touch_id',
      label: 'Đăng nhập bằng Touch ID',
      icon: 'touch_id'
    }
  }

  if (isAndroid) {
    return {
      type: 'fingerprint',
      label: 'Đăng nhập bằng Vân tay / Khuôn mặt',
      icon: 'fingerprint'
    }
  }

  if (isWindows) {
    return {
      type: 'fingerprint',
      label: 'Đăng nhập bằng Windows Hello (Vân tay)',
      icon: 'fingerprint'
    }
  }

  return {
    type: 'biometric',
    label: 'Đăng nhập bằng Sinh trắc học',
    icon: 'fingerprint'
  }
}

export const isBiometricSupported = async () => {
  try {
    if (window.PublicKeyCredential && typeof window.PublicKeyCredential.isUserVerifyingPlatformAuthenticatorAvailable === 'function') {
      const available = await window.PublicKeyCredential.isUserVerifyingPlatformAuthenticatorAvailable()
      return available
    }
    return false
  } catch (err) {
    console.warn('Biometric availability check error:', err)
    return false
  }
}

/**
 * Convert ArrayBuffer to Base64URL string
 */
const bufferToBase64Url = (buffer) => {
  const bytes = new Uint8Array(buffer)
  let string = ''
  for (let i = 0; i < bytes.byteLength; i++) {
    string += String.fromCharCode(bytes[i])
  }
  return btoa(string)
    .replace(/\+/g, '-')
    .replace(/\//g, '_')
    .replace(/=/g, '')
}

/**
 * Convert String to Uint8Array
 */
const stringToUint8Array = (str) => {
  const encoder = new TextEncoder()
  return encoder.encode(str)
}

/**
 * Register Face ID / Touch ID / Fingerprint on current device
 */
export const registerDeviceBiometric = async (user, challengeString = 'picki-biometric-challenge') => {
  if (!window.PublicKeyCredential) {
    throw new Error('Thiết bị không hỗ trợ xác thực Face ID / Vân tay.')
  }

  const userIdStr = String(user.id || user.email || 'user')
  const userDisplayName = user.full_name || user.phone || user.email || 'User'
  const bioInfo = getBiometricType()

  const publicKeyCredentialCreationOptions = {
    challenge: stringToUint8Array(challengeString),
    rp: {
      name: 'Vpick Pickleball App',
      id: window.location.hostname
    },
    user: {
      id: stringToUint8Array(userIdStr),
      name: user.email || user.phone || userIdStr,
      displayName: userDisplayName
    },
    pubKeyCredParams: [
      { alg: -7, type: 'public-key' },
      { alg: -257, type: 'public-key' }
    ],
    authenticatorSelection: {
      authenticatorAttachment: 'platform',
      userVerification: 'required'
    },
    timeout: 60000,
    attestation: 'none'
  }

  const credential = await navigator.credentials.create({
    publicKey: publicKeyCredentialCreationOptions
  })

  if (!credential) {
    throw new Error('Không thể tạo thông tin Face ID / Vân tay.')
  }

  const credentialId = bufferToBase64Url(credential.rawId)
  const publicKeyStr = bufferToBase64Url(credential.response.attestationObject)

  const platform = getDevicePlatform()
  const deviceName = bioInfo.label.replace('Đăng nhập bằng ', '')

  return {
    credential_id: credentialId,
    public_key: publicKeyStr,
    device_name: deviceName,
    platform: platform
  }
}

/**
 * Prompt Face ID / Touch ID / Fingerprint verification for login
 */
export const authenticateWithBiometric = async (challengeString = 'picki-biometric-challenge') => {
  if (!window.PublicKeyCredential) {
    throw new Error('Thiết bị không hỗ trợ xác thực Face ID / Vân tay.')
  }

  const savedCredentialId = localStorage.getItem('vpick_biometric_credential_id')

  const allowCredentials = savedCredentialId ? [{
    id: Uint8Array.from(atob(savedCredentialId.replace(/-/g, '+').replace(/_/g, '/')), c => c.charCodeAt(0)),
    type: 'public-key'
  }] : []

  const publicKeyCredentialRequestOptions = {
    challenge: stringToUint8Array(challengeString),
    allowCredentials: allowCredentials.length > 0 ? allowCredentials : undefined,
    userVerification: 'required',
    timeout: 60000
  }

  const assertion = await navigator.credentials.get({
    publicKey: publicKeyCredentialRequestOptions
  })

  if (!assertion) {
    throw new Error('Xác thực sinh trắc học không thành công.')
  }

  const credentialId = bufferToBase64Url(assertion.rawId)

  return {
    credential_id: credentialId,
    platform: getDevicePlatform()
  }
}
