document.addEventListener('turbo:before-fetch-request', async (event) => {
    event.preventDefault()
    const headers = event.detail.fetchOptions.headers
    if (event.target.tagName === 'HTML') {
        event.detail.resume()
        return
    }
    const turboFrame = event.target.tagName === 'TURBO-FRAME'
        ? event.target
        : event.target.closest('turbo-frame')
    if (!headers['Turbo-Frame'] || !turboFrame?.dataset?.maiTurboContext) {
        event.detail.resume()
        return
    }
    headers['Mai-Turbo-Context'] = turboFrame.dataset.maiTurboContext
    event.detail.fetchOptions.headers = headers
    event.detail.resume()
})
