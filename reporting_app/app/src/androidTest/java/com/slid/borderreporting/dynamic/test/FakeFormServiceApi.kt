package com.slid.borderreporting.dynamic.test

import com.slid.borderreporting.dynamic.model.MobileConfigResponse
import com.slid.borderreporting.dynamic.model.MobileBranding
import com.slid.borderreporting.dynamic.model.MobileLoginRequest
import com.slid.borderreporting.dynamic.model.MobileLoginResponse
import com.slid.borderreporting.dynamic.model.MobileMeResponse
import com.slid.borderreporting.dynamic.model.MobileUserProfile
import com.slid.borderreporting.dynamic.model.SubmissionBatchRequest
import com.slid.borderreporting.dynamic.model.SubmissionBatchResponse
import com.slid.borderreporting.dynamic.remote.FormServiceApi

class FakeFormServiceApi : FormServiceApi {
    var mobileBranding: MobileBranding = MobileBranding()
    var mobileConfigResponse: MobileConfigResponse = MobileConfigResponse()
    var submissionBatchResponse: SubmissionBatchResponse = SubmissionBatchResponse()
    var lastSubmissionBatchRequest: SubmissionBatchRequest? = null
    var throwOnConfig: Exception? = null
    var throwOnSync: Exception? = null
    var loginResponse: MobileLoginResponse = MobileLoginResponse(
        token = "test-token",
        token_type = "Bearer",
        user = MobileUserProfile(id = 1, name = "Test User", email = "test@example.com")
    )

    override suspend fun login(request: MobileLoginRequest): MobileLoginResponse {
        return loginResponse
    }

    override suspend fun getMobileBranding(): MobileBranding {
        return mobileBranding
    }

    override suspend fun me(authorization: String): MobileMeResponse {
        return MobileMeResponse(loginResponse.user)
    }

    override suspend fun logout(authorization: String) = Unit

    override suspend fun getMobileConfig(authorization: String): MobileConfigResponse {
        throwOnConfig?.let { throw it }
        return mobileConfigResponse
    }

    override suspend fun syncSubmissions(
        authorization: String,
        request: SubmissionBatchRequest
    ): SubmissionBatchResponse {
        throwOnSync?.let { throw it }
        lastSubmissionBatchRequest = request
        return submissionBatchResponse
    }
}
