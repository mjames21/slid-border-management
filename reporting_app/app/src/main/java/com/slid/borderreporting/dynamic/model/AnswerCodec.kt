package com.slid.borderreporting.dynamic.model

import kotlinx.serialization.json.Json
import kotlinx.serialization.json.JsonArray
import kotlinx.serialization.json.JsonObject
import kotlinx.serialization.json.JsonPrimitive
import kotlinx.serialization.json.buildJsonArray
import kotlinx.serialization.json.buildJsonObject
import kotlinx.serialization.json.put

object AnswerCodec {
    val json = Json {
        prettyPrint = false
        ignoreUnknownKeys = true
    }

    fun encode(answers: Map<String, List<String>>): String {
        val payload = buildJsonObject {
            answers.forEach { (key, values) ->
                if (values.size <= 1) {
                    put(key, JsonPrimitive(values.firstOrNull().orEmpty()))
                } else {
                    put(
                        key,
                        buildJsonArray {
                            values.forEach { add(JsonPrimitive(it)) }
                        }
                    )
                }
            }
        }
        return json.encodeToString(JsonObject.serializer(), payload)
    }

    fun decode(raw: String): Map<String, List<String>> {
        val obj = json.decodeFromString(JsonObject.serializer(), raw)
        return obj.mapValues { (_, value) ->
            when (value) {
                is JsonPrimitive -> listOf(value.content)
                is JsonArray -> value.mapNotNull { (it as? JsonPrimitive)?.content }
                else -> emptyList()
            }
        }
    }
}
