package com.slid.borderreporting.ui.theme

import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.darkColorScheme
import androidx.compose.material3.lightColorScheme
import androidx.compose.runtime.Composable

private val LightColors = lightColorScheme(
    primary = SlidGreen,
    secondary = SlidGreenLight,
    background = SlidBackground
)

private val DarkColors = darkColorScheme(
    primary = SlidGreenLight,
    secondary = SlidGreen,
    background = SlidGreenDark
)

@Composable
fun SLIDBorderReportingTheme(
    content: @Composable () -> Unit
) {
    MaterialTheme(
        colorScheme = LightColors,
        typography = Typography,
        content = content
    )
}
