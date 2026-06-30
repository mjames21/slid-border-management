package com.slid.borderreporting.dynamic.remote

import com.slid.borderreporting.dynamic.model.MobileConfigResponse
import com.slid.borderreporting.dynamic.model.MobileBranding
import com.slid.borderreporting.dynamic.model.MobileLoginRequest
import com.slid.borderreporting.dynamic.model.MobileLoginResponse
import com.slid.borderreporting.dynamic.model.MobileMeResponse
import com.slid.borderreporting.dynamic.model.SubmissionBatchRequest
import com.slid.borderreporting.dynamic.model.SubmissionBatchResponse
import retrofit2.http.Body
import retrofit2.http.GET
import retrofit2.http.Header
import retrofit2.http.POST

interface FormServiceApi {
    @GET("api/mobile/branding")
    suspend fun getMobileBranding(): MobileBranding

    @POST("api/mobile/auth/login")
    suspend fun login(
        @Body request: MobileLoginRequest
    ): MobileLoginResponse

    @GET("api/mobile/auth/me")
    suspend fun me(
        @Header("Authorization") authorization: String
    ): MobileMeResponse

    @POST("api/mobile/auth/logout")
    suspend fun logout(
        @Header("Authorization") authorization: String
    )

    @GET("api/mobile/config")
    suspend fun getMobileConfig(
        @Header("Authorization") authorization: String
    ): MobileConfigResponse

    @POST("api/mobile/submissions/batch")
    suspend fun syncSubmissions(
        @Header("Authorization") authorization: String,
        @Body request: SubmissionBatchRequest
    ): SubmissionBatchResponse
}
