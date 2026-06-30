package com.slid.borderreporting.dynamic.mrz

import org.junit.Assert.assertEquals
import org.junit.Assert.assertNotNull
import org.junit.Test

class MrzParserTest {

    @Test
    fun parse_readsTd3PassportMrz() {
        val parsed = MrzParser.parse(
            """
            P<UTOERIKSSON<<ANNA<MARIA<<<<<<<<<<<<<<<<<<<
            L898902C36UTO7408122F1204159ZE184226B<<<<<10
            """.trimIndent()
        )

        assertNotNull(parsed)
        requireNotNull(parsed)
        assertEquals(MrzFormat.TD3, parsed.format)
        assertEquals("P", parsed.documentCode)
        assertEquals("UTO", parsed.issuingState)
        assertEquals("ERIKSSON", parsed.primaryIdentifier)
        assertEquals("ANNA MARIA", parsed.secondaryIdentifier)
        assertEquals("L898902C3", parsed.documentNumber)
        assertEquals("UTO", parsed.nationality)
        assertEquals("1974-08-12", parsed.dateOfBirthIso)
        assertEquals("f", parsed.sex)
        assertEquals("2012-04-15", parsed.expiryDateIso)
        assertEquals(MrzCheckResult.PASSED, parsed.checkResult)
    }

    @Test
    fun parse_returnsNullWhenNoMrzLinesArePresent() {
        val parsed = MrzParser.parse("This is ordinary OCR text from the passport cover.")

        assertEquals(null, parsed)
    }
}
