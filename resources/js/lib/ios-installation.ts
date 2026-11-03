export function needsIosInstallation(device: {
    userAgent: string;
    platform: string;
    maxTouchPoints: number;
    standalone?: boolean;
    displayModeStandalone: boolean;
}): boolean {
    const ios =
        /iPad|iPhone|iPod/.test(device.userAgent) ||
        (device.platform === 'MacIntel' && device.maxTouchPoints > 1);

    return ios && !device.standalone && !device.displayModeStandalone;
}

export function readIosInstallationRequired(): boolean {
    return needsIosInstallation({
        userAgent: navigator.userAgent,
        platform: navigator.platform,
        maxTouchPoints: navigator.maxTouchPoints,
        standalone: (navigator as Navigator & { standalone?: boolean })
            .standalone,
        displayModeStandalone: window.matchMedia('(display-mode: standalone)')
            .matches,
    });
}
