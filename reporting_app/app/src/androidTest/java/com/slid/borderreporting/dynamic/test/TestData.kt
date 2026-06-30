package com.slid.borderreporting.dynamic.test

import com.slid.borderreporting.dynamic.local.DynamicDatabase
import com.slid.borderreporting.dynamic.local.DynamicFormDefinitionEntity
import com.slid.borderreporting.dynamic.model.CalculationDefinition
import com.slid.borderreporting.dynamic.model.CalculationKind
import com.slid.borderreporting.dynamic.model.ChoiceOption
import com.slid.borderreporting.dynamic.model.RuleOperator
import com.slid.borderreporting.dynamic.model.RuntimeField
import com.slid.borderreporting.dynamic.model.RuntimeFieldType
import com.slid.borderreporting.dynamic.model.RuntimeFormDefinition
import com.slid.borderreporting.dynamic.model.VisibilityRule
import kotlinx.serialization.json.Json

private val json = Json {
    ignoreUnknownKeys = true
    explicitNulls = false
}

fun simpleRuntimeForm(): RuntimeFormDefinition {
    return RuntimeFormDefinition(
        formId = "slid_test_form",
        version = 1,
        title = "SLID Border Reporting",
        fields = listOf(
            RuntimeField(
                id = "movement_type",
                type = RuntimeFieldType.SELECT_ONE,
                label = "Movement Type",
                required = true,
                listName = "movement_type"
            ),
            RuntimeField(
                id = "traveller_full_name",
                type = RuntimeFieldType.TEXT,
                label = "Traveller Full Name",
                required = true
            ),
            RuntimeField(
                id = "entry_gate_code",
                type = RuntimeFieldType.TEXT,
                label = "Entry Gate Code",
                relevant = VisibilityRule(
                    fieldId = "movement_type",
                    operator = RuleOperator.EQUALS,
                    value = "entry"
                )
            ),
            RuntimeField(
                id = "border_post",
                type = RuntimeFieldType.CALCULATE,
                label = "Border Post",
                calculation = CalculationDefinition(
                    kind = CalculationKind.CONSTANT,
                    value = "Falaba Border Post"
                )
            ),
            RuntimeField(
                id = "border_post_copy",
                type = RuntimeFieldType.CALCULATE,
                label = "Border Post Copy",
                calculation = CalculationDefinition(
                    kind = CalculationKind.COPY,
                    sourceFieldId = "border_post"
                )
            )
        ),
        choiceLists = mapOf(
            "movement_type" to listOf(
                ChoiceOption("entry", "Entry"),
                ChoiceOption("exit", "Exit")
            )
        )
    )
}

suspend fun insertActiveForm(
    db: DynamicDatabase,
    form: RuntimeFormDefinition
) {
    db.formDao().deactivateAll()
    db.formDao().upsert(
        DynamicFormDefinitionEntity(
            id = "${form.formId}:${form.version}",
            formId = form.formId,
            version = form.version,
            title = form.title,
            schemaJson = json.encodeToString(RuntimeFormDefinition.serializer(), form),
            isActive = true,
            downloadedAt = System.currentTimeMillis()
        )
    )
}
