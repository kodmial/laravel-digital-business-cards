{
    modal: @js($initialModal),
    exchangePromptDelayAfterDownload: 650,
    open(name) {
        this.modal = name
    },
    close() {
        this.modal = null
    },
}
