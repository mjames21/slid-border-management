package com.slid.borderreporting.dynamic.mrz

import java.time.LocalDate

enum class MrzFormat(val formValue: String) {
    TD3("td3_two_line_44"),
    TD2("td2_two_line_36"),
    TD1("td1_three_line_30"),
    MRV_A("mrv_a_two_line_44"),
    MRV_B("mrv_b_two_line_36")
}

data class ParsedMrz(
    val format: MrzFormat,
    val lines: List<String>,
    val documentCode: String,
    val issuingState: String,
    val primaryIdentifier: String,
    val secondaryIdentifier: String,
    val documentNumber: String,
    val documentNumberCheckDigit: String,
    val nationality: String,
    val dateOfBirth: String,
    val dateOfBirthIso: String?,
    val dateOfBirthCheckDigit: String,
    val sex: String,
    val expiryDate: String,
    val expiryDateIso: String?,
    val expiryDateCheckDigit: String,
    val optionalData: String,
    val optionalDataCheckDigit: String,
    val compositeCheckDigit: String,
    val checkResult: MrzCheckResult
) {
    val fullName: String
        get() = listOf(primaryIdentifier, secondaryIdentifier)
            .filter { it.isNotBlank() }
            .joinToString(" ")
}

enum class MrzCheckResult(val formValue: String) {
    PASSED("passed"),
    FAILED("failed"),
    MANUAL_REVIEW("manual_review")
}

object MrzParser {
    private val lineCandidateRegex = Regex("[A-Z0-9<]{20,}")

    fun parse(rawText: String): ParsedMrz? {
        return parseLines(extractCandidateLines(rawText))
    }

    fun parseLines(rawLines: List<String>): ParsedMrz? {
        val lines = rawLines
            .map(::normalizeLine)
            .filter { it.length >= 20 && it.contains('<') }

        findLineSet(lines, 3, 30)?.let { return parseTd1(it) }
        findLineSet(lines, 2, 44)?.let { return parseTwoLine(it, 44) }
        findLineSet(lines, 2, 36)?.let { return parseTwoLine(it, 36) }

        return null
    }

    private fun extractCandidateLines(rawText: String): List<String> {
        return rawText
            .lineSequence()
            .flatMap { sourceLine ->
                val normalized = normalizeLine(sourceLine)
                lineCandidateRegex.findAll(normalized).map { it.value }
            }
            .toList()
    }

    private fun findLineSet(lines: List<String>, count: Int, length: Int): List<String>? {
        return lines
            .windowed(count)
            .firstOrNull { window ->
                window.all { it.length >= length - 2 } &&
                    window.first().take(2).any { it.isLetter() }
            }
            ?.map { it.padEnd(length, '<').take(length) }
    }

    private fun parseTwoLine(lines: List<String>, length: Int): ParsedMrz {
        val line1 = lines[0].padEnd(length, '<').take(length)
        val line2 = lines[1].padEnd(length, '<').take(length)
        val isVisa = line1.firstOrNull() == 'V'
        val format = when {
            length == 44 && isVisa -> MrzFormat.MRV_A
            length == 36 && isVisa -> MrzFormat.MRV_B
            length == 44 -> MrzFormat.TD3
            else -> MrzFormat.TD2
        }

        val names = parseNames(line1.substring(5, length))
        val optionalEnd = if (length == 44) 42 else length - 1
        val optionalCheck = if (length == 44) line2.substring(42, 43) else ""
        val compositeCheck = line2.substring(length - 1, length)
        val dateOfBirth = line2.substring(13, 19)
        val expiryDate = line2.substring(21, 27)

        return ParsedMrz(
            format = format,
            lines = lines.map { it.padEnd(length, '<').take(length) },
            documentCode = cleanMrzValue(line1.substring(0, 2)),
            issuingState = cleanMrzValue(line1.substring(2, 5)),
            primaryIdentifier = names.first,
            secondaryIdentifier = names.second,
            documentNumber = cleanMrzValue(line2.substring(0, 9)),
            documentNumberCheckDigit = line2.substring(9, 10),
            nationality = cleanMrzValue(line2.substring(10, 13)),
            dateOfBirth = cleanMrzValue(dateOfBirth),
            dateOfBirthIso = dateOfBirth.toIsoDate(DateKind.BIRTH),
            dateOfBirthCheckDigit = line2.substring(19, 20),
            sex = normalizeSex(line2.substring(20, 21)),
            expiryDate = cleanMrzValue(expiryDate),
            expiryDateIso = expiryDate.toIsoDate(DateKind.EXPIRY),
            expiryDateCheckDigit = line2.substring(27, 28),
            optionalData = cleanMrzValue(line2.substring(28, optionalEnd)),
            optionalDataCheckDigit = cleanMrzValue(optionalCheck),
            compositeCheckDigit = compositeCheck,
            checkResult = verifyTwoLine(line2, length)
        )
    }

    private fun parseTd1(lines: List<String>): ParsedMrz {
        val line1 = lines[0].padEnd(30, '<').take(30)
        val line2 = lines[1].padEnd(30, '<').take(30)
        val line3 = lines[2].padEnd(30, '<').take(30)
        val names = parseNames(line3)
        val dateOfBirth = line2.substring(0, 6)
        val expiryDate = line2.substring(8, 14)

        return ParsedMrz(
            format = MrzFormat.TD1,
            lines = listOf(line1, line2, line3),
            documentCode = cleanMrzValue(line1.substring(0, 2)),
            issuingState = cleanMrzValue(line1.substring(2, 5)),
            primaryIdentifier = names.first,
            secondaryIdentifier = names.second,
            documentNumber = cleanMrzValue(line1.substring(5, 14)),
            documentNumberCheckDigit = line1.substring(14, 15),
            nationality = cleanMrzValue(line2.substring(15, 18)),
            dateOfBirth = cleanMrzValue(dateOfBirth),
            dateOfBirthIso = dateOfBirth.toIsoDate(DateKind.BIRTH),
            dateOfBirthCheckDigit = line2.substring(6, 7),
            sex = normalizeSex(line2.substring(7, 8)),
            expiryDate = cleanMrzValue(expiryDate),
            expiryDateIso = expiryDate.toIsoDate(DateKind.EXPIRY),
            expiryDateCheckDigit = line2.substring(14, 15),
            optionalData = cleanMrzValue(line1.substring(15, 30) + line2.substring(18, 29)),
            optionalDataCheckDigit = "",
            compositeCheckDigit = line2.substring(29, 30),
            checkResult = verifyTd1(line1, line2)
        )
    }

    private fun verifyTwoLine(line2: String, length: Int): MrzCheckResult {
        val checks = mutableListOf<Boolean>()
        checks += verify(line2.substring(0, 9), line2.substring(9, 10))
        checks += verify(line2.substring(13, 19), line2.substring(19, 20))
        checks += verify(line2.substring(21, 27), line2.substring(27, 28))

        if (length == 44) {
            checks += verify(line2.substring(28, 42), line2.substring(42, 43))
            checks += verify(
                line2.substring(0, 10) + line2.substring(13, 20) + line2.substring(21, 43),
                line2.substring(43, 44)
            )
        } else {
            checks += verify(
                line2.substring(0, 10) + line2.substring(13, 20) + line2.substring(21, 35),
                line2.substring(35, 36)
            )
        }

        return checks.toResult()
    }

    private fun verifyTd1(line1: String, line2: String): MrzCheckResult {
        val checks = listOf(
            verify(line1.substring(5, 14), line1.substring(14, 15)),
            verify(line2.substring(0, 6), line2.substring(6, 7)),
            verify(line2.substring(8, 14), line2.substring(14, 15)),
            verify(
                line1.substring(5, 30) + line2.substring(0, 7) + line2.substring(8, 15) + line2.substring(18, 29),
                line2.substring(29, 30)
            )
        )

        return checks.toResult()
    }

    private fun List<Boolean>.toResult(): MrzCheckResult {
        return when {
            isEmpty() -> MrzCheckResult.MANUAL_REVIEW
            all { it } -> MrzCheckResult.PASSED
            else -> MrzCheckResult.FAILED
        }
    }

    private fun verify(value: String, expected: String): Boolean {
        if (expected == "<") return true
        return checkDigit(value).toString() == expected
    }

    private fun checkDigit(value: String): Int {
        val weights = intArrayOf(7, 3, 1)
        return value.mapIndexed { index, char ->
            char.mrzValue() * weights[index % weights.size]
        }.sum() % 10
    }

    private fun Char.mrzValue(): Int {
        return when {
            this in '0'..'9' -> this - '0'
            this in 'A'..'Z' -> this - 'A' + 10
            else -> 0
        }
    }

    private fun parseNames(raw: String): Pair<String, String> {
        val parts = raw.split("<<", limit = 2)
        val primary = cleanName(parts.getOrNull(0).orEmpty())
        val secondary = cleanName(parts.getOrNull(1).orEmpty())
        return primary to secondary
    }

    private fun cleanName(value: String): String {
        return value
            .trim('<')
            .split('<')
            .filter { it.isNotBlank() }
            .joinToString(" ")
    }

    private fun cleanMrzValue(value: String): String {
        return value.trim('<').replace("<", "")
    }

    private fun normalizeSex(raw: String): String {
        return when (raw.uppercase()) {
            "M" -> "m"
            "F" -> "f"
            "X" -> "x"
            else -> "unspecified"
        }
    }

    private fun normalizeLine(raw: String): String {
        return raw
            .uppercase()
            .replace(' ', '<')
            .replace('«', '<')
            .replace('‹', '<')
            .mapNotNull { char ->
                when {
                    char in 'A'..'Z' -> char
                    char in '0'..'9' -> char
                    char == '<' -> char
                    else -> null
                }
            }
            .joinToString("")
            .replace(Regex("<{3,}")) { "<".repeat(it.value.length) }
    }

    private enum class DateKind {
        BIRTH,
        EXPIRY
    }

    private fun String.toIsoDate(kind: DateKind): String? {
        if (!matches(Regex("\\d{6}"))) return null

        val year = substring(0, 2).toInt()
        val month = substring(2, 4).toInt()
        val day = substring(4, 6).toInt()
        val currentTwoDigitYear = LocalDate.now().year % 100
        val century = when (kind) {
            DateKind.BIRTH -> if (year > currentTwoDigitYear) 1900 else 2000
            DateKind.EXPIRY -> 2000
        }

        return runCatching {
            LocalDate.of(century + year, month, day).toString()
        }.getOrNull()
    }
}
